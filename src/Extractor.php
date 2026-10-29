<?php

declare(strict_types=1);

namespace SinglePhp;

use Phar;
use RuntimeException;

class Extractor
{
    /**
     * Extracts all files bundled inside a Single-PHP binary or Phar to $destDir
     */
    public function extract(string $binaryPath, string $destDir): int
    {
        if (!file_exists($binaryPath)) {
            throw new RuntimeException("Binary file not found: {$binaryPath}");
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // 1. Try native Phar extraction directly
        try {
            $phar = new Phar($binaryPath);
            $phar->extractTo($destDir, null, true);
            return $phar->count();
        } catch (\Throwable) {
            // Fall through to manual payload slicer
        }

        // 2. Locate embedded Phar payload if prepended with runner or SAPI
        $content = file_get_contents($binaryPath);
        if ($content === false) {
            throw new RuntimeException("Could not read binary: {$binaryPath}");
        }

        $marker = "__SINGLE_PHP_PAYLOAD__\n";
        $boundPos = strpos($content, $marker);

        if ($boundPos !== false) {
            $pharPayload = substr($content, $boundPos + strlen($marker));
        } else {
            $phpStart = strpos($content, '<' . '?php');
            if ($phpStart === false) {
                throw new RuntimeException("No embedded Phar payload found in {$binaryPath}");
            }
            $pharPayload = substr($content, $phpStart);
        }

        $tempPhar = sys_get_temp_dir() . '/single_php_extracted_' . uniqid() . '.phar';
        file_put_contents($tempPhar, $pharPayload);

        try {
            $phar = new Phar($tempPhar);
            $phar->extractTo($destDir, null, true);
            $count = $phar->count();
            @unlink($tempPhar);
            return $count;
        } catch (\Throwable $nested) {
            @unlink($tempPhar);
            throw new RuntimeException("Failed extracting payload from binary: " . $nested->getMessage());
        }
    }
}
