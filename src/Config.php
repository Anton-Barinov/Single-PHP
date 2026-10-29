<?php

declare(strict_types=1);

namespace SinglePhp;

use InvalidArgumentException;
use RuntimeException;

class Config
{
    private string $projectDir;
    private string $name;
    private string $version;
    private string $entrypoint;
    private string $output;
    private string $engine;
    private string $target;
    private array $exclude;
    private array $include;
    private array $extensions;
    private array $phpIni;
    private array $env;
    private bool $minify;

    public function __construct(string $projectDir, array $options = [])
    {
        $this->projectDir = rtrim(realpath($projectDir) ?: $projectDir, '/');
        if (!is_dir($this->projectDir)) {
            throw new InvalidArgumentException("Project directory does not exist: {$projectDir}");
        }

        // 1. Try reading composer.json
        $composer = $this->loadComposerJson();

        // 2. Try reading single-php.json
        $configJson = $this->loadSinglePhpJson();

        // 3. Resolve Name
        $this->name = $options['name']
            ?? $configJson['name']
            ?? $composer['name']
            ?? basename($this->projectDir);
        $this->name = str_replace('/', '-', $this->name);

        // 4. Resolve Version
        $this->version = $options['version']
            ?? $configJson['version']
            ?? $composer['version']
            ?? '1.0.0';

        // 5. Resolve Entrypoint
        $this->entrypoint = $options['entrypoint']
            ?? $configJson['entrypoint']
            ?? $this->detectEntrypoint($composer);

        // 6. Resolve Target Architecture
        $this->target = strtolower($options['target']
            ?? $configJson['target']
            ?? 'host');

        // 7. Resolve Output Binary Path
        $defaultOutput = 'dist/' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $this->name);
        if ($this->target === 'windows-x64' && !str_ends_with($defaultOutput, '.exe')) {
            $defaultOutput .= '.exe';
        }

        $this->output = $options['output']
            ?? $configJson['output']
            ?? $defaultOutput;

        if ($this->target === 'windows-x64' && !str_ends_with($this->output, '.exe')) {
            $this->output .= '.exe';
        }

        // 8. Resolve Engine (micro, franken, phar)
        $this->engine = strtolower($options['engine']
            ?? $configJson['engine']
            ?? 'micro');

        // 9. Minify flag
        $this->minify = (bool)($options['minify']
            ?? $configJson['minify']
            ?? false);

        // 10. Environment variables
        $this->env = array_merge(
            $configJson['env'] ?? [],
            $options['env'] ?? []
        );

        // 9. Exclude patterns
        $defaultExclude = [
            '.git',
            '.github',
            '.idea',
            '.vscode',
            'tests',
            'test',
            'phpunit.xml',
            'phpunit.xml.dist',
            'phpcs.xml',
            'phpstan.neon',
            '*.log',
            'dist',
            'build',
            'vendor/**/tests',
            'vendor/**/Tests',
            'vendor/**/.git',
        ];
        $this->exclude = array_unique(array_merge(
            $defaultExclude,
            $configJson['exclude'] ?? [],
            $options['exclude'] ?? []
        ));

        $this->include = $options['include']
            ?? $configJson['include']
            ?? [];

        $this->extensions = $options['extensions']
            ?? $configJson['extensions']
            ?? ['curl', 'mbstring', 'openssl', 'pdo_sqlite', 'json', 'pcntl', 'posix'];

        $this->phpIni = array_merge(
            ['memory_limit' => '512M', 'short_open_tag' => 'Off'],
            $configJson['ini'] ?? [],
            $options['ini'] ?? []
        );
    }

    public static function fromDir(string $dir, array $options = []): self
    {
        return new self($dir, $options);
    }

    private function loadComposerJson(): array
    {
        $file = $this->projectDir . '/composer.json';
        if (file_exists($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function loadSinglePhpJson(): array
    {
        $file = $this->projectDir . '/single-php.json';
        if (file_exists($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function detectEntrypoint(array $composer): string
    {
        // 1. Check composer bin property
        if (isset($composer['bin'])) {
            $bins = (array)$composer['bin'];
            if (!empty($bins) && is_string($bins[0]) && file_exists($this->projectDir . '/' . $bins[0])) {
                return $bins[0];
            }
        }

        // 2. Check candidate entry files
        $candidates = [
            'bin/console',
            'bin/app',
            'bin/' . basename($this->projectDir),
            'app.php',
            'main.php',
            'cli.php',
            'run.php',
            'index.php',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($this->projectDir . '/' . $cand)) {
                return $cand;
            }
        }

        // 3. Fallback to index.php or first php file
        $files = glob($this->projectDir . '/*.php');
        if (!empty($files)) {
            return basename($files[0]);
        }

        throw new RuntimeException("Could not detect entrypoint in '{$this->projectDir}'. Please specify --entrypoint=<file>.");
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getEntrypoint(): string
    {
        return $this->entrypoint;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function getInclude(): array
    {
        return $this->include;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function getPhpIni(): array
    {
        return $this->phpIni;
    }

    public function isMinify(): bool
    {
        return $this->minify;
    }

    public function getEnv(): array
    {
        return $this->env;
    }

    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'version'    => $this->version,
            'entrypoint' => $this->entrypoint,
            'engine'     => $this->engine,
            'target'     => $this->target,
            'output'     => $this->output,
            'minify'     => $this->minify,
            'env'        => $this->env,
            'extensions' => $this->extensions,
            'exclude'    => $this->exclude,
            'ini'        => $this->phpIni,
        ];
    }
}
