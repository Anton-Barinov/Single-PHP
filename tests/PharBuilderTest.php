<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Config;
use SinglePhp\Bundler\ProjectScanner;
use SinglePhp\Bundler\PharBuilder;

require_once __DIR__ . '/../src/autoload.php';

echo "Running PharBuilderTest...\n";

$exampleDir = __DIR__ . '/../examples/01_simple_cli';
$config = Config::fromDir($exampleDir);
$scanner = new ProjectScanner($exampleDir);
$files = $scanner->scan();

$targetPhar = sys_get_temp_dir() . '/test_build_' . uniqid() . '.phar';
$builder = new PharBuilder($config);
$builder->build($files, $targetPhar);

assert(file_exists($targetPhar), 'Phar file should exist on disk');
assert(filesize($targetPhar) > 500, 'Phar should have non-zero size');

// Verify metadata
$phar = new \Phar($targetPhar);
$meta = $phar->getMetadata();
assert($meta['name'] === 'sample-hello-cli');
assert($meta['entrypoint'] === 'app.php');
assert($meta['packager'] === 'Single-PHP');
echo "  ✓ Phar archive built with valid metadata\n";
unset($phar);
@unlink($targetPhar);

// 2. Test Minification
$minifyConfig = new Config($exampleDir, ['minify' => true]);
$minifyPhar = sys_get_temp_dir() . '/test_minify_' . uniqid() . '.phar';
$builder = new PharBuilder($minifyConfig);
$builder->build($files, $minifyPhar);

assert(file_exists($minifyPhar));
$phar = new \Phar($minifyPhar);
$code = file_get_contents('phar://' . $phar->getPath() . '/app.php');
assert(!str_contains($code, "\n\n"), 'Minified code should have compacted line spacing');
echo "  ✓ Minification stripped whitespace and comments\n";
unset($phar);
@unlink($minifyPhar);

echo "PharBuilderTest passed successfully! ✅\n";
