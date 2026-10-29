<?php

declare(strict_types=1);

namespace SinglePhp;

use InvalidArgumentException;
use RuntimeException;
use SinglePhp\Bundler\ProjectScanner;
use SinglePhp\Bundler\PharBuilder;
use SinglePhp\Engines\EngineInterface;
use SinglePhp\Engines\MicroEngine;
use SinglePhp\Engines\FrankenEngine;
use SinglePhp\Engines\PharEngine;

class Packager
{
    private Config $config;
    /** @var array<string, EngineInterface> */
    private array $engines = [];

    public function __construct(Config $config)
    {
        $this->config = $config;

        // Register default engines
        $this->registerEngine(new MicroEngine());
        $this->registerEngine(new FrankenEngine());
        $this->registerEngine(new PharEngine());
    }

    public function registerEngine(EngineInterface $engine): self
    {
        $this->engines[$engine->getName()] = $engine;
        return $this;
    }

    /**
     * Executes the full packaging workflow
     *
     * @param callable|null $logger Optional callback for progress logging fn(string $msg)
     * @return array Build summary report
     */
    public function package(?callable $logger = null): array
    {
        $log = $logger ?? fn(string $msg) => null;
        $startTime = microtime(true);

        $log("Scanning project files in {$this->config->getProjectDir()}...");

        // 1. Scan files
        $scanner = new ProjectScanner(
            $this->config->getProjectDir(),
            $this->config->getExclude(),
            $this->config->getInclude()
        );
        $files = $scanner->scan();

        if (empty($files)) {
            throw new RuntimeException("No files found to package in {$this->config->getProjectDir()}");
        }

        $log("✓ Found " . count($files) . " project files");

        // 2. Build intermediate Phar payload
        $tempPhar = sys_get_temp_dir() . '/single_php_' . uniqid() . '.phar';
        $log("Building compressed payload archive...");

        $builder = new PharBuilder($this->config);
        $builder->build($files, $tempPhar);
        $pharSize = filesize($tempPhar);

        $log("✓ Payload archive built (" . round($pharSize / 1024, 2) . " KB)");

        // 3. Resolve engine
        $engineName = $this->config->getEngine();
        if (!isset($this->engines[$engineName])) {
            @unlink($tempPhar);
            throw new InvalidArgumentException("Unknown engine '{$engineName}'. Available: " . implode(', ', array_keys($this->engines)));
        }

        $engine = $this->engines[$engineName];
        $target = $this->config->getTarget();

        if (!$engine->isSupported($target)) {
            @unlink($tempPhar);
            throw new RuntimeException("Engine '{$engineName}' does not support target architecture '{$target}'");
        }

        // 4. Resolve output path
        $outputPath = $this->config->getOutput();
        if (!str_starts_with($outputPath, '/')) {
            $outputPath = $this->config->getProjectDir() . '/' . $outputPath;
        }

        $log("Compiling standalone binary using {$engineName} engine for {$target}...");

        $finalBinary = $engine->build($tempPhar, $outputPath, $this->config);
        @unlink($tempPhar);

        $finalSize = filesize($finalBinary);
        $elapsed = round(microtime(true) - $startTime, 3);

        $log("✓ Standalone binary created at {$finalBinary} (" . round($finalSize / 1024 / 1024, 2) . " MB) in {$elapsed}s");

        return [
            'name'        => $this->config->getName(),
            'version'     => $this->config->getVersion(),
            'engine'      => $engineName,
            'target'      => $target,
            'filesCount'  => count($files),
            'pharSize'    => $pharSize,
            'binarySize'  => $finalSize,
            'outputPath'  => $finalBinary,
            'elapsedSec'  => $elapsed,
        ];
    }
}
