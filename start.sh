#!/usr/bin/env bash
# Launch the PHP Online Compiler.
# Usage:  ./start.sh             (defaults to PHP 8.5 on port 8080)
#         ./start.sh 9000        (custom port)
#         ./start.sh 8080 8.4    (custom port + server PHP version)

set -euo pipefail

PORT="${1:-8080}"
SERVER_PHP="${2:-8.5}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! command -v "php${SERVER_PHP}" >/dev/null 2>&1; then
    echo "PHP ${SERVER_PHP} not found. Available:"
    ls /usr/bin/php?.? 2>/dev/null || true
    exit 1
fi

echo "═══════════════════════════════════════════════════════════"
echo "  🐘 PHP Online Compiler"
echo "  Built-in server: PHP ${SERVER_PHP}"
echo "  URL:             http://localhost:${PORT}"
echo "  Project dir:     ${DIR}"
echo "  Press Ctrl+C to stop"
echo "═══════════════════════════════════════════════════════════"

cd "$DIR"
exec "php${SERVER_PHP}" -S "0.0.0.0:${PORT}" -t .
