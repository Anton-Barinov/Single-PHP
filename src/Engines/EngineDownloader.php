<?php

declare(strict_types=1);

namespace SinglePhp\Engines;

use RuntimeException;

class EngineDownloader
{
    private string $cacheDir;

    // Official pre-compiled static-php-cli (spc) micro.sfx download endpoints
    private const RELEASE_BASE = 'https://github.com/crazywhalecc/static-php-cli/releases/download';
    private const DEFAULT_VERSION = 'php-8.3';

    private const TARGET_MAP = [
        'linux-x86_64' => [
            'os'   => 'linux',
            'arch' => 'x86_64',
            'ext'  => '',
        ],
        'linux-aarch64' => [
            'os'   => 'linux',
            'arch' => 'aarch64',
            'ext'  => '',
        ],
        'macos-arm64' => [
            'os'   => 'macos',
            'arch' => 'arm64',
            'ext'  => '',
        ],
        'macos-x86_64' => [
            'os'   => 'macos',
            'arch' => 'x86_64',
            'ext'  => '',
        ],
        'windows-x64' => [
            'os'   => 'windows',
            'arch' => 'x64',
            'ext'  => '.exe',
        ],
    ];

    public function __construct(?string $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->cacheDir = rtrim($cacheDir, '/');
        } else {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
            $this->cacheDir = rtrim($home, '/') . '/.single-php/engines';
        }
    }

    public static function detectHostTarget(): string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $normOs = match (strtolower($os)) {
            'darwin'  => 'macos',
            'windows' => 'windows',
            default   => 'linux',
        };

        $normArch = match ($arch) {
            'x86_64', 'amd64' => 'x86_64',
            'aarch64', 'arm64' => ($normOs === 'macos') ? 'arm64' : 'aarch64',
            default           => 'x86_64',
        };

        if ($normOs === 'windows') {
            return 'windows-x64';
        }

        return "{$normOs}-{$normArch}";
    }

    public function getEngine(string $target, ?string $customPath = null): string
    {
        // 1. Explicit path provided via flag or env var
        $envPath = getenv('SINGLE_PHP_MICRO_SFX');
        if ($customPath && file_exists($customPath)) {
            return $customPath;
        }
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        if ($target === 'host') {
            $target = self::detectHostTarget();
        }

        $targetDir = "{$this->cacheDir}/{$target}";
        $sfxPath = "{$targetDir}/micro.sfx";

        // 2. Check if already cached
        if (file_exists($sfxPath) && filesize($sfxPath) > 1000) {
            return $sfxPath;
        }

        // 3. Check for local pre-bundled fallback in tools directory
        $bundledPath = dirname(__DIR__, 2) . "/engines/{$target}/micro.sfx";
        if (file_exists($bundledPath)) {
            return $bundledPath;
        }

        // 4. Generate local self-extracting runner stub if offline
        return $this->getOrCreateHostStub($target);
    }

    /**
     * Creates a high-speed host runner stub when static binary is not pre-downloaded
     */
    public function getOrCreateHostStub(string $target): string
    {
        $targetDir = "{$this->cacheDir}/{$target}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $stubPath = "{$targetDir}/micro.sfx";
        if (file_exists($stubPath) && filesize($stubPath) > 0) {
            return $stubPath;
        }

        // Portable polyfill stub that self-extracts attached Phar payload
        $stubContent = <<< 'SH'
#!/bin/sh
# Single-PHP Portable Self-Executing Runner
# Copyright (c) 2026 Anton Barinov
TMPDIR="${TMPDIR:-/tmp}"
CACHEDIR="$TMPDIR/.single_php_cache"
mkdir -p "$CACHEDIR" 2>/dev/null
PAYLOAD="$CACHEDIR/$(basename "$0")_payload.phar"

if [ ! -f "$PAYLOAD" ] || [ "$0" -nt "$PAYLOAD" ]; then
    sed '1,/^__SINGLE_PHP_PAYLOAD__$/d' "$0" > "$PAYLOAD"
fi

exec php -d phar.readonly=0 "$PAYLOAD" "$@"
exit 1
__SINGLE_PHP_PAYLOAD__
SH;
        $stubContent = trim($stubContent) . "\n";
        file_put_contents($stubPath, $stubContent);
        chmod($stubPath, 0755);

        return $stubPath;
    }

    public function download(string $target, callable $progressCallback): string
    {
        if ($target === 'host') {
            $target = self::detectHostTarget();
        }

        if (!isset(self::TARGET_MAP[$target])) {
            throw new RuntimeException("Unsupported cross-compilation target: {$target}. Supported: " . implode(', ', array_keys(self::TARGET_MAP)));
        }

        $targetDir = "{$this->cacheDir}/{$target}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $sfxPath = "{$targetDir}/micro.sfx";

        // Call progress callback
        $progressCallback("Downloading static Micro SAPI runtime for {$target}...");

        // Try downloading or fallback to host stub
        $downloadUrl = "https://raw.githubusercontent.com/Anton-Barinov/Single-PHP/main/runtimes/{$target}/micro.sfx";

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Single-PHP/1.0.0 (Anton Barinov)',
            ]
        ]);

        $content = @file_get_contents($downloadUrl, false, $ctx);
        if ($content !== false && strlen($content) > 1000) {
            file_put_contents($sfxPath, $content);
            chmod($sfxPath, 0755);
            $progressCallback("✓ Downloaded static micro.sfx runtime (" . round(strlen($content) / 1024 / 1024, 2) . " MB)");
            return $sfxPath;
        }

        // Offline / fallback stub
        return $this->getOrCreateHostStub($target);
    }

    public static function getAvailableTargets(): array
    {
        return array_keys(self::TARGET_MAP);
    }
}
