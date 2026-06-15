<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Reject anything that isn't POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$code    = (string)($payload['code']    ?? '');
$version = (string)($payload['version'] ?? '8.5');
$stdin   = (string)($payload['stdin']   ?? '');

// Limit code size
if (strlen($code) > 200_000) {
    echo json_encode(['error' => 'Code too large (max 200 KB)']);
    exit;
}

// Whitelist PHP versions to prevent command injection via the version field
$allowed = ['8.5', '8.4', '8.3', '8.2', '8.0', '7.4', '5.6'];
if (!in_array($version, $allowed, true)) {
    echo json_encode(['error' => 'Unsupported PHP version']);
    exit;
}

$phpBinary = "/usr/bin/php{$version}";
if (!is_executable($phpBinary)) {
    echo json_encode(['error' => "PHP {$version} is not installed on this server"]);
    exit;
}

// Make sure the user code starts with <?php so even raw snippets execute
$source = ltrim($code);
if (!str_starts_with($source, '<?php') && !str_starts_with($source, '<?=')) {
    $source = "<?php\n" . $source;
}

// Write code to a temporary file
$tmpDir = sys_get_temp_dir() . '/php-online-compiler';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0700, true);
}
$tmpFile = tempnam($tmpDir, 'code_') . '.php';
file_put_contents($tmpFile, $source);

// Safety options:
//   -d display_errors=1 to surface errors
//   -d memory_limit=128M
//   -d max_execution_time=10
//   -d disable_functions=...  (block file/system writes)
$disable = implode(',', [
    'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
    'pcntl_exec', 'pcntl_fork',
    'mail',
    'symlink', 'link',
    'curl_multi_exec',
]);

$args = [
    $phpBinary,
    '-d', 'display_errors=1',
    '-d', 'display_startup_errors=1',
    '-d', 'error_reporting=E_ALL',
    '-d', 'memory_limit=128M',
    '-d', 'max_execution_time=10',
    '-d', "disable_functions={$disable}",
    '-d', 'open_basedir=' . $tmpDir,
    '-d', 'allow_url_fopen=0',
    '-d', 'allow_url_include=0',
    '-f', $tmpFile,
];

// Wrap with timeout(1) as a hard kill switch (PHP's max_execution_time
// can be bypassed by external calls / sleep on some setups).
$cmd = ['/usr/bin/timeout', '--kill-after=2', '12'];
$cmd = array_merge($cmd, $args);

$descriptors = [
    0 => ['pipe', 'r'], // stdin
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w'], // stderr
];

$started = microtime(true);
$process = proc_open($cmd, $descriptors, $pipes, $tmpDir, [
    'HOME' => $tmpDir,
    'PATH' => '/usr/bin:/bin',
]);

if (!is_resource($process)) {
    @unlink($tmpFile);
    echo json_encode(['error' => 'Failed to start PHP']);
    exit;
}

// Pipe in the user-supplied stdin
fwrite($pipes[0], $stdin);
fclose($pipes[0]);

// Read with a wall-clock cap as a belt-and-braces measure
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$stdout = '';
$stderr = '';
$deadline = microtime(true) + 13.0;

while (true) {
    $status = proc_get_status($process);
    $chunkOut = stream_get_contents($pipes[1]);
    $chunkErr = stream_get_contents($pipes[2]);
    if ($chunkOut !== false) $stdout .= $chunkOut;
    if ($chunkErr !== false) $stderr .= $chunkErr;

    if (!$status['running']) break;
    if (microtime(true) > $deadline) {
        proc_terminate($process, 9);
        break;
    }

    // Cap output to 256 KB
    if (strlen($stdout) > 262_144 || strlen($stderr) > 262_144) {
        proc_terminate($process, 9);
        $stderr .= "\n[Output truncated: exceeded 256 KB limit]\n";
        break;
    }

    usleep(20_000);
}

// Drain any remaining bytes
$stdout .= stream_get_contents($pipes[1]) ?: '';
$stderr .= stream_get_contents($pipes[2]) ?: '';

fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$elapsed = microtime(true) - $started;

@unlink($tmpFile);

// timeout(1) returns 124 when the wall-clock limit triggers
$timedOut = ($exitCode === 124 || $exitCode === 137);
if ($timedOut && $stderr === '') {
    $stderr = "[Execution timed out after 10 seconds]";
}

// Strip the temp file path from error messages so users see "main.php" instead
$displayName = 'main.php';
$stdout = str_replace($tmpFile, $displayName, $stdout);
$stderr = str_replace($tmpFile, $displayName, $stderr);

// Pull memory usage out of stderr if PHP emitted one (it won't for normal runs;
// instead we just report N/A for now). We could parse a custom probe later.
$memory = '—';

echo json_encode([
    'stdout'    => $stdout,
    'stderr'    => $stderr,
    'exit_code' => $exitCode,
    'time'      => number_format($elapsed, 3, '.', ''),
    'memory'    => $memory,
    'timed_out' => $timedOut,
    'version'   => $version,
], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
