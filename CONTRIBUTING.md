# Contributing to Single-PHP

Thank you for contributing to **Single-PHP**!

## Getting Started

1. **Fork the Repository** on GitHub.
2. **Create a Feature Branch**:
   ```bash
   git checkout -b feature/cool-engine
   ```
3. **Run Unit Tests**:
   Ensure all tests pass:
   ```bash
   php tests/run_tests.php
   ```
4. **Submit a Pull Request**:
   Explain your changes, rationale, and any platform-specific testing performed (Linux x86_64, macOS arm64, etc.).

## Guidelines
- Strict types (`declare(strict_types=1);`).
- Zero external runtime dependencies in core.
- Compatibility with PHP 8.1+.
