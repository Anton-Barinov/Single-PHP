<?php

declare(strict_types=1);

namespace SinglePhp\Bundler;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ProjectScanner
{
    private string $root;
    private array $excludePatterns;
    private array $includePatterns;

    public function __construct(string $root, array $excludePatterns = [], array $includePatterns = [])
    {
        $this->root = rtrim(realpath($root) ?: $root, '/');
        $this->excludePatterns = $this->loadIgnoreFile($excludePatterns);
        $this->includePatterns = $includePatterns;
    }

    private function loadIgnoreFile(array $defaults): array
    {
        $ignoreFile = $this->root . '/.single-php-ignore';
        if (file_exists($ignoreFile)) {
            $lines = file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                        $defaults[] = $trimmed;
                    }
                }
            }
        }
        return array_unique($defaults);
    }

    /**
     * @return array<string, string> Map of relative path in archive => absolute path on disk
     */
    public function scan(): array
    {
        $files = [];
        $dirIterator = new RecursiveDirectoryIterator(
            $this->root,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS
        );
        $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::LEAVES_ONLY);

        $rootLen = strlen($this->root) + 1;

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absPath = $fileInfo->getPathname();
            $relPath = substr($absPath, $rootLen);

            // Normalize path separators to forward slash
            $relPath = str_replace('\\', '/', $relPath);

            if ($this->isExcluded($relPath)) {
                continue;
            }

            $files[$relPath] = $absPath;
        }

        return $files;
    }

    private function isExcluded(string $relPath): bool
    {
        // 1. Check if explicitly included
        foreach ($this->includePatterns as $pattern) {
            if ($this->matchPattern($pattern, $relPath)) {
                return false;
            }
        }

        // 2. Check exclusion patterns
        foreach ($this->excludePatterns as $pattern) {
            if ($this->matchPattern($pattern, $relPath)) {
                return true;
            }
        }

        return false;
    }

    private function matchPattern(string $pattern, string $path): bool
    {
        $pattern = trim($pattern, '/');

        // Exact match
        if ($pattern === $path) {
            return true;
        }

        // Directory prefix match (e.g. '.git' matches '.git/HEAD')
        if (str_starts_with($path, $pattern . '/')) {
            return true;
        }

        // Wildcard / fnmatch
        if (fnmatch($pattern, $path, FNM_PATHNAME) || fnmatch($pattern, basename($path))) {
            return true;
        }

        // Recursive glob (e.g. vendor/**/tests)
        if (str_contains($pattern, '**')) {
            $regex = '#^' . str_replace('\*\*', '.*', preg_quote($pattern, '#')) . '#';
            $regex = str_replace('\*', '[^/]*', $regex);
            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }
}
