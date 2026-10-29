<?php

declare(strict_types=1);

namespace SinglePhp\Engines;

use SinglePhp\Config;

interface EngineInterface
{
    /**
     * Engine Name (e.g. 'micro', 'franken', 'phar')
     */
    public function getName(): string;

    /**
     * Whether the engine supports the requested target architecture
     */
    public function isSupported(string $target): bool;

    /**
     * Builds the final standalone executable binary from the payload Phar
     *
     * @return string Final output binary path
     */
    public function build(string $pharPath, string $outputPath, Config $config): string;
}
