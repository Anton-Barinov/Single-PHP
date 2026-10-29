<?php

declare(strict_types=1);

namespace SinglePhp\Engines;

use RuntimeException;
use SinglePhp\Config;

class FrankenEngine implements EngineInterface
{
    public function getName(): string
    {
        return 'franken';
    }

    public function isSupported(string $target): bool
    {
        return true;
    }

    public function build(string $pharPath, string $outputPath, Config $config): string
    {
        if (!file_exists($pharPath)) {
            throw new RuntimeException("Payload Phar does not exist: {$pharPath}");
        }

        $outDir = dirname($outputPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        // Check if frankenphp binary is available locally
        $frankenBinary = exec('which frankenphp') ?: null;

        if ($frankenBinary && file_exists($frankenBinary)) {
            // Embed or package with FrankenPHP runner
            $cmd = escapeshellcmd($frankenBinary) . ' php-cli ' . escapeshellarg($pharPath);
            $wrapper = <<< 'SH'
#!/bin/sh
# Single-PHP FrankenPHP Runner Wrapper
exec frankenphp php-cli "$0" "$@"
exit 1

SH;
            file_put_contents($outputPath, $wrapper . file_get_contents($pharPath));
        } else {
            // Self-contained portable runner wrapper
            $wrapper = <<< 'SH'
#!/bin/sh
# Single-PHP Web / CLI Runner (Powered by FrankenPHP / Static PHP)
if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp php-cli "$0" "$@"
elif command -v php >/dev/null 2>&1; then
    exec php -d phar.readonly=0 "$0" "$@"
else
    echo "Error: No runtime found to execute Single-PHP bundle." >&2
    exit 1
fi
exit 1

SH;
            file_put_contents($outputPath, $wrapper . file_get_contents($pharPath));
        }

        chmod($outputPath, 0755);
        return $outputPath;
    }
}
