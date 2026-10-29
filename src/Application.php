<?php

declare(strict_types=1);

namespace SinglePhp;

use SinglePhp\Engines\EngineDownloader;
use Throwable;

class Application
{
    public const VERSION = '1.0.0';

    public function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        $command = $args[0] ?? 'help';

        // Parse flags (--flag=val, -t val, etc.)
        $parsed = $this->parseArguments($args);

        switch ($command) {
            case 'build':
                return $this->commandBuild($parsed);

            case 'init':
                return $this->commandInit($parsed);

            case 'inspect':
                return $this->commandInspect($parsed);

            case 'extract':
                return $this->commandExtract($parsed);

            case 'targets':
                return $this->commandTargets();

            case 'download-engines':
                return $this->commandDownloadEngines($parsed);

            case 'version':
            case '-v':
            case '--version':
                $this->printBanner();
                return 0;

            case 'help':
            case '-h':
            case '--help':
            default:
                $this->commandHelp();
                return 0;
        }
    }

    private function commandBuild(array $parsed): int
    {
        $dir = $parsed['positionals'][1] ?? '.';
        $dir = realpath($dir) ?: $dir;

        echo "\n⚡ Single-PHP Compiler v" . self::VERSION . "\n";
        echo "=========================================================\n";

        try {
            $options = [];
            if (isset($parsed['options']['entrypoint'])) {
                $options['entrypoint'] = $parsed['options']['entrypoint'];
            }
            if (isset($parsed['options']['output'])) {
                $options['output'] = $parsed['options']['output'];
            }
            if (isset($parsed['options']['engine'])) {
                $options['engine'] = $parsed['options']['engine'];
            }
            if (isset($parsed['options']['target'])) {
                $options['target'] = $parsed['options']['target'];
            }

            $config = Config::fromDir($dir, $options);
            $packager = new Packager($config);

            $summary = $packager->package(function (string $msg) {
                echo "  {$msg}\n";
            });

            echo "\n🎉 Build Succeeded!\n";
            echo "---------------------------------------------------------\n";
            echo "  Binary Name:  {$summary['name']} (v{$summary['version']})\n";
            echo "  Engine:       {$summary['engine']}\n";
            echo "  Target:       {$summary['target']}\n";
            echo "  Bundled:      {$summary['filesCount']} files\n";
            echo "  Binary Size:  " . round($summary['binarySize'] / 1024 / 1024, 2) . " MB\n";
            echo "  Output Path:  {$summary['outputPath']}\n";
            echo "  Build Time:   {$summary['elapsedSec']} seconds\n";
            echo "---------------------------------------------------------\n";
            echo "To run your binary: {$summary['outputPath']}\n\n";

            return 0;
        } catch (Throwable $e) {
            echo "\n❌ Build Failed: " . $e->getMessage() . "\n\n";
            return 1;
        }
    }

    private function commandInit(array $parsed): int
    {
        $dir = $parsed['positionals'][1] ?? '.';
        $dir = realpath($dir) ?: $dir;
        $file = $dir . '/single-php.json';

        if (file_exists($file)) {
            echo "Configuration file already exists: {$file}\n";
            return 0;
        }

        $config = Config::fromDir($dir);
        $json = json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($file, $json);

        echo "✓ Created single-php.json in {$dir}\n";
        return 0;
    }

    private function commandInspect(array $parsed): int
    {
        $binary = $parsed['positionals'][1] ?? null;
        if (!$binary || !file_exists($binary)) {
            fwrite(STDERR, "Usage: single-php inspect <binary_path>\n");
            return 1;
        }

        $inspector = new Inspector();
        $data = $inspector->inspect($binary);

        echo "\n=== Single-PHP Binary Inspection ===\n\n";
        echo "  File:         {$data['binaryPath']}\n";
        echo "  Format:       {$data['format']}\n";
        echo "  Size:         {$data['fileSizeHuman']}\n";
        echo "  Phar Payload: " . ($data['hasPharPayload'] ? 'Found (offset: ' . $data['pharOffset'] . ')' : 'None') . "\n";

        if (!empty($data['manifest'])) {
            echo "\n  Embedded Manifest:\n";
            foreach ($data['manifest'] as $k => $v) {
                echo "    - {$k}: {$v}\n";
            }
        }

        if (!empty($data['files'])) {
            echo "\n  Bundled Files (" . count($data['files']) . "):\n";
            foreach (array_slice($data['files'], 0, 10) as $f) {
                echo "    - {$f['path']} (" . number_format($f['size']) . " bytes)\n";
            }
            if (count($data['files']) > 10) {
                echo "    ... and " . (count($data['files']) - 10) . " more files\n";
            }
        }
        echo "\n";

        return 0;
    }

    private function commandExtract(array $parsed): int
    {
        $binary = $parsed['positionals'][1] ?? null;
        $dest = $parsed['positionals'][2] ?? './extracted';

        if (!$binary) {
            fwrite(STDERR, "Usage: single-php extract <binary_path> [destination_dir]\n");
            return 1;
        }

        try {
            $extractor = new Extractor();
            $count = $extractor->extract($binary, $dest);
            echo "✓ Successfully extracted {$count} files to {$dest}\n";
            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            return 1;
        }
    }

    private function commandTargets(): int
    {
        echo "\nAvailable Cross-Compilation Targets:\n";
        echo "-------------------------------------\n";
        echo "  host           Current operating system (" . EngineDownloader::detectHostTarget() . ")\n";
        foreach (EngineDownloader::getAvailableTargets() as $target) {
            echo "  {$target}\n";
        }
        echo "\n";
        return 0;
    }

    private function commandDownloadEngines(array $parsed): int
    {
        $target = $parsed['positionals'][1] ?? 'host';
        $downloader = new EngineDownloader();

        try {
            $path = $downloader->download($target, function (string $msg) {
                echo "  {$msg}\n";
            });
            echo "✓ Runtime ready at {$path}\n";
            return 0;
        } catch (Throwable $e) {
            echo "Error downloading engine: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    private function printBanner(): void
    {
        $version = self::VERSION;
        echo <<<BANNER
⚡ Single-PHP v{$version} — Micro-Packager for PHP
Compile PHP applications & composer projects into single self-executing binaries.
Maintained by Anton Barinov <https://github.com/Anton-Barinov>

BANNER;
    }

    private function commandHelp(): void
    {
        $this->printBanner();
        echo <<<HELP
Usage:
  single-php <command> [options] [arguments]

Commands:
  build [dir]                Compile project into a single standalone binary
  init [dir]                 Generate a single-php.json configuration file
  inspect <binary>           Inspect embedded metadata and files inside a binary
  extract <binary> [dest]    Unpack a compiled binary back into source files
  targets                    List supported target architectures
  download-engines [target]  Download static Micro SAPI runtimes for cross-compilation
  version                    Show version information
  help                       Show this help screen

Options for build:
  --entrypoint=<file>        Relative path to entrypoint script (e.g. bin/console, app.php)
  --output=<path>            Output binary path (default: dist/<name>)
  --engine=<micro|franken|phar> Packaging engine (default: micro)
  --target=<target>          Target architecture (host, linux-x86_64, macos-arm64, etc.)

Examples:
  # Pack current directory into a standalone binary:
  single-php build

  # Build for Linux x86_64 using Micro SAPI:
  single-php build --target=linux-x86_64 --output=dist/myapp-linux

  # Build standalone Phar archive:
  single-php build --engine=phar --output=dist/myapp.phar

  # Inspect a compiled binary:
  single-php inspect dist/myapp

HELP;
    }

    private function parseArguments(array $args): array
    {
        $positionals = [];
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            } elseif (str_starts_with($arg, '-') && strlen($arg) > 1) {
                $options[substr($arg, 1)] = true;
            } else {
                $positionals[] = $arg;
            }
        }

        return ['positionals' => $positionals, 'options' => $options];
    }
}
