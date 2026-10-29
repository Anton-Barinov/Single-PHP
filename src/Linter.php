<?php

declare(strict_types=1);

namespace SinglePhp;

class Linter
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Lints the project files and configuration
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function check(): array
    {
        $errors = [];
        $warnings = [];

        // 1. Entrypoint Existence
        $entrypointPath = $this->config->getProjectDir() . '/' . $this->config->getEntrypoint();
        if (!file_exists($entrypointPath)) {
            $errors[] = "Entrypoint script not found: {$this->config->getEntrypoint()} in {$this->config->getProjectDir()}";
        } else {
            // 2. Syntax check on Entrypoint
            $output = [];
            $cmd = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($entrypointPath);
            exec($cmd, $output, $returnVar);
            if ($returnVar !== 0) {
                $errors[] = "PHP Syntax Error in entrypoint ({$this->config->getEntrypoint()}): " . implode(' ', $output);
            }
        }

        // 3. Check composer.json if present
        $composerFile = $this->config->getProjectDir() . '/composer.json';
        if (file_exists($composerFile)) {
            $raw = file_get_contents($composerFile);
            $json = json_decode((string)$raw, true);
            if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Invalid JSON syntax in composer.json: " . json_last_error_msg();
            }
        }

        // 4. Output directory writability
        $output = $this->config->getOutput();
        if (!str_starts_with($output, '/')) {
            $output = $this->config->getProjectDir() . '/' . $output;
        }
        $targetDir = dirname($output);
        while (!file_exists($targetDir) && $targetDir !== '/' && dirname($targetDir) !== $targetDir) {
            $targetDir = dirname($targetDir);
        }
        if (file_exists($targetDir) && !is_writable($targetDir)) {
            $errors[] = "Output directory or parent path is not writable: {$targetDir}";
        }

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }
}
