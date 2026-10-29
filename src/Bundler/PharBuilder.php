<?php

declare(strict_types=1);

namespace SinglePhp\Bundler;

use Phar;
use RuntimeException;
use SinglePhp\Config;

class PharBuilder
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Builds the standalone Phar archive and returns the file path
     *
     * @param array<string, string> $files Map of relative path => absolute path
     */
    public function build(array $files, string $targetPharPath): string
    {
        if (ini_get('phar.readonly') === '1') {
            throw new RuntimeException("Phar write support is disabled in php.ini (phar.readonly = On). Run Single-PHP with -d phar.readonly=0 or via bin/single-php.");
        }

        $dir = dirname($targetPharPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($targetPharPath)) {
            unlink($targetPharPath);
        }

        $pharAlias = 'single_php_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $this->config->getName()) . '.phar';
        $phar = new Phar($targetPharPath, 0, $pharAlias);

        $phar->startBuffering();

        // 1. Add all scanned project files
        foreach ($files as $relPath => $absPath) {
            $phar->addFile($absPath, $relPath);
        }

        // 2. Set metadata
        $manifest = [
            'packager'        => 'Single-PHP',
            'packagerVersion' => '1.0.0',
            'name'            => $this->config->getName(),
            'version'         => $this->config->getVersion(),
            'entrypoint'      => $this->config->getEntrypoint(),
            'engine'          => $this->config->getEngine(),
            'target'          => $this->config->getTarget(),
            'builtAt'         => date('c'),
            'fileCount'       => count($files),
        ];
        $phar->setMetadata($manifest);

        // 3. Generate bootstrap stub
        $stub = $this->generateStub($pharAlias, $this->config->getEntrypoint());
        $phar->setStub($stub);

        $phar->stopBuffering();

        // 4. Optionally compress files if gzip is available
        if (Phar::canCompress(Phar::GZ)) {
            try {
                // Compress individual files (keeps the stub uncompressed for Micro SAPI)
                $phar->compressFiles(Phar::GZ);
            } catch (\Exception) {
                // Keep uncompressed if compression fails
            }
        }

        return $targetPharPath;
    }

    private function generateStub(string $alias, string $entrypoint): string
    {
        $iniStatements = '';
        foreach ($this->config->getPhpIni() as $key => $val) {
            $iniStatements .= "ini_set(" . var_export((string)$key, true) . ", " . var_export((string)$val, true) . ");\n";
        }

        return <<<STUB
#!/usr/bin/env php
<?php
/**
 * Single-PHP Standalone Executable Stub
 * Packaged with Single-PHP by Anton Barinov (https://github.com/Anton-Barinov)
 */
Phar::mapPhar('{$alias}');

{$iniStatements}

// Check for vendor/autoload.php in phar
if (file_exists('phar://{$alias}/vendor/autoload.php')) {
    require_once 'phar://{$alias}/vendor/autoload.php';
}

// Execute Entrypoint
require 'phar://{$alias}/{$entrypoint}';

__HALT_COMPILER();
STUB;
    }
}
