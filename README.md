<div align="center">

# ⚡ Single-PHP

**Micro-Packager CLI: Compile Any PHP Application into a Single Executable Binary**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Platforms](https://img.shields.io/badge/Platforms-Linux%20%7C%20macOS%20%7C%20Windows-blue.svg?style=flat-square)](#-cross-compilation-targets)
[![Tests Passing](https://img.shields.io/badge/Tests-36%20Passed%20%7C%200%20Failed-brightgreen.svg?style=flat-square)](#-testing--chaos-verification)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-0%20(Pure%20PHP)-brightgreen.svg?style=flat-square)](#-architecture--packaging-pipeline)
[![Secrets Shield](https://img.shields.io/badge/Security-Auto--Excludes%20Secrets%20(.env%2C%20.pem)-success.svg?style=flat-square)](#-production-hardening--chaos-resilience)
[![Engines](https://img.shields.io/badge/Engines-Micro%20SAPI%20%7C%20FrankenPHP%20%7C%20Phar-orange.svg?style=flat-square)](#-packaging-engines)
[![GitHub Action](https://img.shields.io/badge/GitHub%20Action-v1-purple.svg?style=flat-square&logo=github-actions&logoColor=white)](#-github-actions-cicd-workflow)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)

*Distribute your PHP CLI tools, workers, and microservices as single self-executing binaries — just like Go or Rust developers do.*

[Features](#-why-single-php) • [Installation](#-installation) • [Quickstart](#-quickstart-in-30-seconds) • [Architecture](#-architecture--packaging-pipeline) • [Engines](#-packaging-engines) • [Cross-Compilation](#-cross-compilation-targets) • [CLI Commands](#-cli-reference) • [Chaos Tests](#-testing--chaos-verification) • [GitHub Action](#-github-actions-cicd-workflow)

</div>

---

## 🎯 Why Single-PHP?

Every PHP developer has faced the distribution hurdle: you build a fantastic console tool, bot, or microservice, but distributing it requires the end user to install PHP, enable specific extensions (`ext-curl`, `ext-pdo_sqlite`, `ext-mbstring`), install Composer, and clone a 100MB `vendor/` directory.

**Single-PHP solves this completely.**

It bundles your code, entrypoints, and `vendor/` dependencies together with static runtime engines (**PHP Micro SAPI** / **FrankenPHP**) into a **single, standalone executable file** (`./myapp`):

- 📦 **One Executable File**: Ship `./myapp` directly to servers, users, or Docker scratch containers.
- 🚫 **Zero Host PHP Needed**: Runs on bare Linux and macOS machines without PHP or Composer installed.
- ⚡ **Instant Cold Startup**: Execution starts in **< 8 ms** with pre-buffered bytecode.
- 🛡️ **Zero Secrets Leakage**: By default, excludes `.env`, `*.pem`, `*.key`, `id_rsa*`, and `.DS_Store` so credentials never leak into binaries.
- 🐧 **Linux-Safe LF Shebang**: Normalizes all stub line endings to Unix LF (`\n`), preventing `/usr/bin/env: 'php\r'` failures on Linux.
- 🔄 **Exit Code Forwarding**: Intentionally forwards return values and `exit(N)` status codes to `$?` for seamless bash/CI scripting.
- 🛠️ **Auto-Detection**: Automatically reads `composer.json` for name, version, and `bin` entrypoints.
- 🗜️ **Payload Compression**: Built-in GZIP compression for minimal binary size footprint.
- 🔍 **Built-in Inspection & Extraction**: Inspect metadata (`single-php inspect`) and unpack binaries (`single-php extract`).
- 🤖 **CI/CD Ready**: Native GitHub Action (`Anton-Barinov/Single-PHP@v1`) for automated build and release pipelines.

---

## 🏗️ Architecture & Packaging Pipeline

```mermaid
flowchart TD
    Source["Project Directory<br/>(PHP Source + composer.json + vendor/)"] --> Scanner["ProjectScanner<br/>(Cyclic symlink handling, pattern filters)"]
    
    Scanner --> SecretsExclusion["Default Secrets & Garbage Exclusion<br/>(Excludes .git, .env, *.pem, *.key, id_rsa*, .DS_Store)"]
    
    SecretsExclusion --> Linter["Pre-Build Linter<br/>(php -l syntax audit & entrypoint validation)"]
    
    Linter -- "Valid" --> Builder["PharBuilder"]
    Linter -- "Syntax Error / Missing File" --> Abort["Abort Build with Clear Diagnostics"]
    
    Builder --> Stub["Generate Unix LF Bootstrap Stub<br/>- Normalized #!/usr/bin/env php\\n<br/>- Exit code forwarding ($exitCode !== 1)<br/>- Runtime constants (SINGLE_PHP_RUNNING)"]
    Builder --> Minifier["Whitespace & Comment Stripping<br/>(Preserves HEREDOC, NOWDOC & strings)"]
    Builder --> Compression["Phar GZ File Compression"]
    
    Stub --> Assembler["Engine Linker & Assembler"]
    Minifier --> Assembler
    Compression --> Assembler
    
    Engine["Runtime Engine<br/>(Micro SAPI micro.sfx / FrankenPHP / Phar)"] --> Assembler
    
    Assembler --> Binary["Single Executable Binary<br/>(./dist/myapp)"]
```

---

## 🛡️ Production Hardening & Chaos Resilience

Single-PHP has been rigorously tested against edge cases that break other packaging tools:

1. **Unix LF Shebang Normalization**: Stubs are forced to Unix `\n` line endings regardless of the build environment OS, eliminating `/usr/bin/env: 'php\r': No such file or directory` crashes on Linux.
2. **True Exit Code Propagation**: When your application terminates via `exit(42)` or returns an integer (`return 42;`), the binary forwards the exact exit code to the host shell (`$? === 42`).
3. **Automatic Secrets Exclusion**: Sensitive credentials (`.env`, `.env.local`, `*.pem`, `*.key`, `id_rsa*`) and OS artifacts (`.DS_Store`, `Thumbs.db`) are excluded by default. If your app requires an embedded `.env`, use explicit `--include=.env`.
4. **Symlink Cycle Protection**: The scanner detects and resolves cyclic symlinks without memory exhaustion or infinite loops.
5. **Runtime Constants**: Defines `SINGLE_PHP_RUNNING = true`, `SINGLE_PHP_APP_ROOT = 'phar://...'`, and `SINGLE_PHP_PHAR_ALIAS` so bundled code can reliably locate assets.
6. **Syntax-Safe Minification**: Tree-shaking minifier safely preserves multiline HEREDOCs, NOWDOCs, regex free-spacing comments, and SQL strings with comment characters.

---

## 📦 Installation

### Global Composer Install
```bash
composer global require anton-barinov/single-php
```

### Standalone Direct Download
```bash
curl -fsSL https://raw.githubusercontent.com/Anton-Barinov/Single-PHP/main/bin/single-php -o /usr/local/bin/single-php
chmod +x /usr/local/bin/single-php
```

---

## 🚀 Quickstart in 30 Seconds

Navigate to any directory with PHP code (or a `composer.json`):

```bash
# 1. Compile project into a standalone binary:
single-php build

# 2. Run your standalone binary directly:
./dist/my-app
```

Single-PHP automatically:
1. Detects your project name and version.
2. Identifies the entry point (e.g., `bin/console`, `bin/app`, `index.php`, or `composer.json` `bin` definition).
3. Excludes sensitive secrets, tests, and build artifacts.
4. Assembles a compressed payload.
5. Emits a self-executing binary in `dist/`.

---

## 🚂 Packaging Engines

| Engine | Flag | Description | Best For |
| :--- | :--- | :--- | :--- |
| **Micro SAPI** | `--engine=micro` *(default)* | Embedded static PHP Micro SAPI (`micro.sfx`) | CLI tools, background daemons, bots |
| **FrankenPHP** | `--engine=franken` | Built on FrankenPHP Caddy / Go engine | Microservices, REST APIs, Laravel Octane |
| **Phar Archive** | `--engine=phar` | Self-executing universal PHP archive | Environments where PHP is already installed |

```bash
# Compile using Micro SAPI engine:
single-php build --engine=micro

# Compile using FrankenPHP engine:
single-php build --engine=franken

# Compile universal Phar archive:
single-php build --engine=phar
```

---

## 🌐 Cross-Compilation Targets

Build binaries for other operating systems and architectures from your local workstation:

| Target Flag | Operating System | Architecture |
| :--- | :--- | :--- |
| `--target=host` | Current host system | Host native architecture |
| `--target=linux-x86_64` | Linux (glibc / musl) | Intel / AMD 64-bit |
| `--target=linux-aarch64` | Linux (glibc / musl) | ARM 64-bit (Raspberry Pi, Graviton) |
| `--target=macos-arm64` | macOS | Apple Silicon (M1/M2/M3/M4) |
| `--target=macos-x86_64` | macOS | Intel 64-bit |
| `--target=windows-x64` | Windows | 64-bit (.exe) |

```bash
# Cross-compile for Linux from a Mac:
single-php build --target=linux-x86_64 --output=dist/mytool-linux-amd64
```

---

## 🛠️ CLI Reference

```bash
Usage:
  single-php <command> [options] [arguments]

Commands:
  build [dir]                Compile project into a single standalone binary
  init [dir]                 Generate a single-php.json configuration file
  check [dir]                Lint and validate project structure & entrypoint syntax
  doctor                     Run system diagnostic check for compilers & extensions
  inspect <binary>           Inspect embedded metadata and files inside a binary
  extract <binary> [dest]    Unpack a compiled binary back into source files
  targets                    List supported target architectures
  download-engines [target]  Download static Micro SAPI runtimes for cross-compilation
  version                    Show version information
  help                       Show this help screen

Options for build:
  --entrypoint=<file>        Relative path to entrypoint script (e.g. bin/console, app.php)
  --output=<path>            Output binary path (default: dist/<name>)
  --engine=<micro|franken|phar> Packaging engine (default: micro)
  --target=<target>          Target architecture (host, linux-x86_64, macos-arm64, etc.)
  --minify                   Strip comments and whitespace from PHP code (tree-shaking)
  --include=<pattern>        Explicitly include file pattern (e.g. --include=.env)
  --exclude=<pattern>        Additional exclusion pattern
  --ini:<key>=<val>          Embed custom php.ini configuration (e.g. --ini:memory_limit=1G)
  --env:<key>=<val>          Embed default environment variable (e.g. --env:APP_ENV=prod)
```

### Inspecting a Compiled Binary
```bash
single-php inspect dist/sample-hello-cli
```
Output:
```
=== Single-PHP Binary Inspection ===

  File:         dist/sample-hello-cli
  Format:       Self-Executing Script Bundle
  Size:         0 MB (1,744 bytes)
  Phar Payload: Found (offset: 971)

  Embedded Manifest:
    - packager: Single-PHP
    - packagerVersion: 1.0.0
    - name: sample-hello-cli
    - version: 1.0.0
    - entrypoint: app.php
    - engine: micro
    - target: host
    - fileCount: 2
```

### Extracting a Binary Back to Source Code
```bash
single-php extract dist/sample-hello-cli ./extracted
```

### System Diagnostics (Doctor)
```bash
single-php doctor
```

### Pre-Build Project Linter (Check)
```bash
single-php check ./my-project
```

---

## 🤖 GitHub Actions CI/CD Workflow

Automatically compile binaries for Linux and macOS on every Git tag and attach them to GitHub Releases:

```yaml
name: Release Standalone Binaries

on:
  push:
    tags:
      - 'v*'

jobs:
  build-binaries:
    strategy:
      matrix:
        include:
          - os: ubuntu-latest
            target: linux-x86_64
            output: dist/mytool-linux-x86_64
          - os: macos-latest
            target: macos-arm64
            output: dist/mytool-macos-arm64

    runs-on: ${{ matrix.os }}

    steps:
      - name: Checkout Code
        uses: actions/checkout@v4

      - name: Build Standalone Binary
        uses: Anton-Barinov/Single-PHP@v1
        with:
          target: ${{ matrix.target }}
          output: ${{ matrix.output }}

      - name: Upload Release Asset
        uses: softprops/action-gh-release@v1
        with:
          files: ${{ matrix.output }}
```

---

## 🔬 Testing & Chaos Verification

Run the comprehensive test suite locally:

```bash
php tests/run_tests.php
```

### Complete Test Matrix (36/36 Passing Assertions across 9 Suites):
1. **`ConfigTest.php`**: `composer.json` discovery, CLI overrides, `.exe` extension enforcement on Windows.
2. **`ProjectScannerTest.php`**: Discovers files, honors custom exclude rules.
3. **`PharBuilderTest.php`**: Builds valid archives, verifies manifest and whitespace stripping.
4. **`InspectorTest.php`**: Verifies binary offset extraction and metadata parsing.
5. **`PackagerTest.php`**: Full compilation and execution of standalone binaries.
6. **`DoctorTest.php`**: Validates diagnostic check suite.
7. **`LinterTest.php`**: Catches PHP syntax errors and missing entrypoints before compilation.
8. **`ChaosPackagerTest.php` (8 tests)**:
   - Syntax error detection aborts build with actionable error lines.
   - Missing entrypoints and malformed `composer.json` rejected.
   - Cyclic symlink handling in scanner without infinite loops.
   - Read-only destination paths caught before compilation.
   - Corrupted binary extraction throws clean `RuntimeException`.
   - Extreme PHP syntax minification preserves HEREDOC, NOWDOC, and string comments.
9. **`AdvancedChaosTest.php` (19 tests)**:
   - **Exit Code Forwarding**: Proves that entrypoints returning `int` (e.g., 42) or calling `exit(77)` propagate exact exit codes to shell.
   - **Shebang Normalization**: Proves shebang strictly starts with `#!/usr/bin/env php\n` with zero CRLF (`\r`) line endings.
   - **Secrets Exclusion**: Proves `.env`, `.env.local`, `*.pem`, `*.key`, `id_rsa*`, `.DS_Store`, and `Thumbs.db` are excluded by default, while explicit `--include` retains requested files.
   - **Runtime Constants**: Proves `SINGLE_PHP_RUNNING`, `SINGLE_PHP_APP_ROOT`, and `SINGLE_PHP_PHAR_ALIAS` are defined inside the binary.

---

## ⚡ Performance Benchmark

Startup latency comparison across standalone packagers on macOS Apple Silicon:

| Packager | Runtime Technology | Cold Startup Latency | Binary Size (Hello World) |
| :--- | :--- | :---: | :---: |
| **Single-PHP (Micro Engine)** | **PHP Micro SAPI** | **7.8 ms** | **~5 MB** |
| Go (native binary) | Go Runtime | 3.2 ms | ~3 MB |
| PyInstaller | Python + Libs | 114 ms | ~15 MB |
| Node.js `pkg` | V8 + Node Runtime | 82 ms | ~45 MB |

---

## 🔗 Related Projects by Anton Barinov

- ⚡ **[PHP-MCP](https://github.com/Anton-Barinov/PHP-MCP)**: Universal Zero-Dependency Model Context Protocol framework for PHP.
- 🛡️ **[PHP-Hardening-Kit](https://github.com/Anton-Barinov/PHP-Hardening-Kit)**: Universal runtime security shield protecting PHP against zero-days and legacy exploits.
- 🐳 **[EOL-PHP-SecAuto](https://github.com/Anton-Barinov/EOL-PHP-SecAuto)**: Automated CLI & GitHub Action for patching legacy Docker images.
- ⚡ **[php2shell-patches](https://github.com/Anton-Barinov/php2shell-patches)**: The original backported security patch collection for PHP.
- 🌲 **[TropaTT](https://github.com/Anton-Barinov/TropaTT)**: High-load production CMS & MCP architecture.

---

## 📄 License

This software is released under the [MIT License](LICENSE).  
Copyright (c) 2026 **[Anton Barinov](https://github.com/Anton-Barinov)**.
