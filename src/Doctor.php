<?php

declare(strict_types=1);

namespace SinglePhp;

use Phar;
use SinglePhp\Engines\EngineDownloader;

class Doctor
{
    /**
     * Runs full system diagnostics for Single-PHP
     *
     * @return array<array{status: string, title: string, message: string}>
     */
    public function diagnose(): array
    {
        $checks = [];

        // 1. PHP Version
        $phpVer = PHP_VERSION;
        if (version_compare($phpVer, '8.1.0', '>=')) {
            $checks[] = [
                'status'  => 'ok',
                'title'   => 'PHP Runtime Version',
                'message' => "PHP {$phpVer} (Supported >= 8.1)",
            ];
        } else {
            $checks[] = [
                'status'  => 'error',
                'title'   => 'PHP Runtime Version',
                'message' => "PHP {$phpVer} is outdated. Single-PHP requires PHP 8.1+",
            ];
        }

        // 2. Phar Extension
        if (extension_loaded('phar')) {
            $readonly = ini_get('phar.readonly');
            $checks[] = [
                'status'  => 'ok',
                'title'   => 'Phar Extension',
                'message' => 'Phar extension loaded (API v' . Phar::apiVersion() . ', phar.readonly=' . ($readonly ? 'On' : 'Off') . ')',
            ];
        } else {
            $checks[] = [
                'status'  => 'error',
                'title'   => 'Phar Extension',
                'message' => 'The phar extension is required to compile archives.',
            ];
        }

        // 3. Compression Algorithms
        $compressionSupported = [];
        if (Phar::canCompress(Phar::GZ)) {
            $compressionSupported[] = 'gzip';
        }
        if (Phar::canCompress(Phar::BZ2)) {
            $compressionSupported[] = 'bzip2';
        }

        if (!empty($compressionSupported)) {
            $checks[] = [
                'status'  => 'ok',
                'title'   => 'Phar Compression',
                'message' => 'Supported algorithms: ' . implode(', ', $compressionSupported),
            ];
        } else {
            $checks[] = [
                'status'  => 'warning',
                'title'   => 'Phar Compression',
                'message' => 'No compression extensions found (zlib/bz2). Archives will be uncompressed.',
            ];
        }

        // 4. Host Architecture
        $hostTarget = EngineDownloader::detectHostTarget();
        $checks[] = [
            'status'  => 'ok',
            'title'   => 'Host Architecture',
            'message' => "Detected target: {$hostTarget} (" . php_uname('s') . ' ' . php_uname('m') . ')',
        ];

        // 5. Cache Directory Writability
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
        $cacheDir = rtrim($home, '/') . '/.single-php';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        if (is_writable($cacheDir)) {
            $checks[] = [
                'status'  => 'ok',
                'title'   => 'Cache Directory',
                'message' => "Cache path writable: {$cacheDir}",
            ];
        } else {
            $checks[] = [
                'status'  => 'error',
                'title'   => 'Cache Directory',
                'message' => "Cannot write to cache path: {$cacheDir}",
            ];
        }

        // 6. FrankenPHP Availability
        $frankenPath = exec('which frankenphp 2>/dev/null') ?: null;
        if ($frankenPath && file_exists($frankenPath)) {
            $checks[] = [
                'status'  => 'ok',
                'title'   => 'FrankenPHP Engine',
                'message' => "FrankenPHP detected at: {$frankenPath}",
            ];
        } else {
            $checks[] = [
                'status'  => 'info',
                'title'   => 'FrankenPHP Engine',
                'message' => 'FrankenPHP not detected in PATH (optional, used for --engine=franken)',
            ];
        }

        return $checks;
    }
}
