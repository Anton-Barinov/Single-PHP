<?php

declare(strict_types=1);

namespace SinglePhp\Engines;

use RuntimeException;
use SinglePhp\Config;

class MicroEngine implements EngineInterface
{
    private EngineDownloader $downloader;
    private ?string $customEnginePath;

    public function __construct(?EngineDownloader $downloader = null, ?string $customEnginePath = null)
    {
        $this->downloader = $downloader ?? new EngineDownloader();
        $this->customEnginePath = $customEnginePath;
    }

    public function getName(): string
    {
        return 'micro';
    }

    public function isSupported(string $target): bool
    {
        $supported = array_merge(['host'], EngineDownloader::getAvailableTargets());
        return in_array(strtolower($target), $supported, true);
    }

    public function build(string $pharPath, string $outputPath, Config $config): string
    {
        if (!file_exists($pharPath)) {
            throw new RuntimeException("Payload Phar does not exist: {$pharPath}");
        }

        $target = $config->getTarget();
        $sfxPath = $this->downloader->getEngine($target, $this->customEnginePath);

        if (!file_exists($sfxPath)) {
            throw new RuntimeException("Micro SAPI engine binary not found for target '{$target}' at {$sfxPath}");
        }

        $outDir = dirname($outputPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        // 1. Read Micro SAPI binary
        $sfxData = file_get_contents($sfxPath);
        if ($sfxData === false || strlen($sfxData) === 0) {
            throw new RuntimeException("Failed reading Micro SAPI runtime from {$sfxPath}");
        }

        // 2. Read Phar payload
        $pharData = file_get_contents($pharPath);
        if ($pharData === false || strlen($pharData) === 0) {
            throw new RuntimeException("Failed reading payload Phar from {$pharPath}");
        }

        // 3. Concatenate SAPI binary + Phar payload
        $binary = $sfxData . $pharData;

        $written = file_put_contents($outputPath, $binary);
        if ($written === false || $written !== strlen($binary)) {
            throw new RuntimeException("Failed writing compiled standalone binary to {$outputPath}");
        }

        // 4. Set executable permission
        chmod($outputPath, 0755);

        return $outputPath;
    }
}
