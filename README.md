# PHP Online Compiler

A browser-based PHP playground that runs code against the latest PHP versions (5.6 → 8.5) on the server. Built with a Monaco editor frontend and a sandboxed PHP backend.

![PHP 8.5](https://img.shields.io/badge/PHP-8.5-7b68ee?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)

## Features

- **Multi-version runtime** — switch between PHP 5.6, 7.4, 8.0, 8.2, 8.3, 8.4 and 8.5 from the toolbar.
- **Latest language features** — bundled examples cover PHP 8.5 (`|>` pipe, `array_first/last`, `#[\NoDiscard]`), 8.4 (property hooks, asymmetric visibility), 8.3 (typed class constants), 8.2 (readonly classes), 8.1 (enums) and earlier.
- **Real editor** — Monaco (the VS Code editor) with PHP syntax highlighting, line numbers and `Ctrl+Enter` to run.
- **Stdin support** — pipe values into `fgets(STDIN)` / `readline()` from a dedicated panel.
- **Share links** — base64-encoded URL hashes carry your code, no backend storage required.
- **Sandboxed execution** — 10s timeout, 128M memory cap, `open_basedir` jail, dangerous functions disabled, 256 KB output cap.

## Run locally

```bash
git clone https://github.com/sathish2598/php-online-compiler.git
cd php-online-compiler
./start.sh                # http://localhost:8080
./start.sh 9000           # custom port
./start.sh 8080 8.4       # custom port + which PHP runs the web server itself
```

The launcher uses PHP's built-in web server. The PHP versions you can *target* from the UI depend on which `/usr/bin/php{X.Y}` binaries are installed on the host.

### Install all supported PHP versions (Ubuntu / Debian)

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php5.6-cli php7.4-cli php8.0-cli php8.2-cli php8.3-cli php8.4-cli php8.5-cli
```

## Project layout

```
.
├── index.html   # UI + Monaco editor + examples
├── run.php      # JSON endpoint: receives {code, version, stdin}, spawns php{version}
├── start.sh     # Launcher for the built-in dev server
└── README.md
```

## Deploying

This project needs a server that can execute PHP — it will **not** run on GitHub Pages (static hosting only).

Suitable hosts:

- **A VPS / EC2 / DigitalOcean droplet** — install PHP versions as above, then run `./start.sh` behind nginx, or serve `index.html` and `run.php` directly with `apache2` + `mod_php`.
- **Render / Railway / fly.io** — use a custom Dockerfile that installs the PHP versions you want to expose.
- **Shared PHP hosting** — drop `index.html` and `run.php` into the doc root, but version-switching only works if the host has multiple `php{X.Y}` binaries.

### Example Dockerfile

```dockerfile
FROM ubuntu:22.04
RUN apt-get update && apt-get install -y software-properties-common \
 && add-apt-repository ppa:ondrej/php -y \
 && apt-get update \
 && apt-get install -y php5.6-cli php7.4-cli php8.0-cli php8.2-cli php8.3-cli php8.4-cli php8.5-cli \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . /app
EXPOSE 8080
CMD ["php8.5", "-S", "0.0.0.0:8080", "-t", "/app"]
```

## Security notes

`run.php` runs untrusted user code, so it ships with defense in depth:

- Hard wall-clock limit via `timeout(1)` (`SIGKILL` after 12s).
- PHP `max_execution_time=10`, `memory_limit=128M`.
- `open_basedir` confines filesystem access to a per-run temp dir.
- `disable_functions` blocks `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `pcntl_exec/fork`, `mail`, `symlink`, `link`.
- `allow_url_fopen=0`, `allow_url_include=0`.
- 200 KB input cap, 256 KB output cap, version whitelist (no shell injection via the version field).

For a public-facing deployment, run the PHP process inside an unprivileged container or as a low-privilege user, and put a rate limiter / WAF in front of `run.php`.

## License

MIT
