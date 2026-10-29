<?php

declare(strict_types=1);

namespace SinglePhp;

use Phar;
use RuntimeException;

class Inspector
{
    /**
     * Inspects a compiled Single-PHP binary or Phar file
     */
    public function inspect(string $binaryPath): array
    {
        if (!file_exists($binaryPath)) {
            throw new RuntimeException("Binary file not found: {$binaryPath}");
        }

        $fileSize = filesize($binaryPath);
        $contents = file_get_contents($binaryPath);
        if ($contents === false) {
            throw new RuntimeException("Failed reading binary: {$binaryPath}");
        }

        // 1. Locate Phar signature (__HALT_COMPILER();)
        $haltPos = strpos($contents, '__HALT_COMPILER();');
        $hasHalt = $haltPos !== false;

        $type = 'unknown';
        if (str_starts_with($contents, "\x7fELF")) {
            $type = 'Linux ELF Binary (Static Micro SAPI)';
        } elseif (str_starts_with($contents, "\xcf\xfa\xed\xfe") || str_starts_with($contents, "\xfe\xed\xfa\xcf") || str_starts_with($contents, "\xca\xfe\xba\xbe")) {
            $type = 'macOS Mach-O Binary (Static Micro SAPI)';
        } elseif (str_starts_with($contents, "MZ")) {
            $type = 'Windows PE Executable (Static Micro SAPI)';
        } elseif (str_starts_with($contents, '#!/bin/sh') || str_starts_with($contents, '#!/usr/bin/env')) {
            $type = 'Self-Executing Script Bundle';
        } elseif ($hasHalt) {
            $type = 'Standard PHP Archive (Phar)';
        }

        // 2. Read embedded Phar metadata
        $manifest = [];
        $files = [];

        $pharTarget = $binaryPath;
        $tempPhar = null;

        $marker = "__SINGLE_PHP_PAYLOAD__\n";
        $boundPos = strpos($contents, $marker);
        if ($boundPos !== false) {
            $pharPayload = substr($contents, $boundPos + strlen($marker));
            $tempPhar = sys_get_temp_dir() . '/single_php_insp_' . uniqid() . '.phar';
            file_put_contents($tempPhar, $pharPayload);
            $pharTarget = $tempPhar;
        }

        try {
            $phar = new Phar($pharTarget);
            $meta = $phar->getMetadata();
            if (is_array($meta)) {
                $manifest = $meta;
            }

            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                /** @var \PharFileInfo $file */
                $files[] = [
                    'path'       => $file->getPathname(),
                    'size'       => $file->getSize(),
                    'compressed' => $file->isCompressed(),
                ];
            }
        } catch (\Throwable) {
            // If native Phar cannot parse direct concatenated binary without alias
        } finally {
            if ($tempPhar && file_exists($tempPhar)) {
                @unlink($tempPhar);
            }
        }

        return [
            'binaryPath'     => realpath($binaryPath) ?: $binaryPath,
            'fileSize'       => $fileSize,
            'fileSizeHuman'  => round($fileSize / 1024 / 1024, 2) . ' MB (' . number_format($fileSize) . ' bytes)',
            'format'         => $type,
            'hasPharPayload' => $hasHalt,
            'pharOffset'     => $hasHalt ? $haltPos : null,
            'manifest'       => $manifest,
            'files'          => $files,
        ];
    }
}
