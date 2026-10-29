<?php

declare(strict_types=1);

namespace SinglePhp\Engines;

use RuntimeException;
use SinglePhp\Config;

class PharEngine implements EngineInterface
{
    public function getName(): string
    {
        return 'phar';
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

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        copy($pharPath, $outputPath);
        chmod($outputPath, 0755);

        return $outputPath;
    }
}
