<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Config;
use SinglePhp\Linter;

require_once __DIR__ . '/../src/autoload.php';

echo "Running LinterTest...\n";

// 1. Test Valid Project
$validDir = __DIR__ . '/../examples/01_simple_cli';
$config = Config::fromDir($validDir);
$linter = new Linter($config);
$res = $linter->check();

assert($res['valid'] === true, 'Valid project should pass linter');
assert(empty($res['errors']), 'Valid project should have no errors');
echo "  ✓ Valid project passed linter check\n";

// 2. Test Missing Entrypoint
$tempDir = sys_get_temp_dir() . '/linter_broken_' . uniqid();
mkdir($tempDir, 0755, true);
file_put_contents($tempDir . '/composer.json', json_encode(['name' => 'broken', 'bin' => ['missing.php']]));

$brokenConfig = Config::fromDir($tempDir);
$brokenLinter = new Linter($brokenConfig);
$brokenRes = $brokenLinter->check();

assert($brokenRes['valid'] === false, 'Missing entrypoint should fail linter');
assert(count($brokenRes['errors']) > 0, 'Should have error message for missing entrypoint');
echo "  ✓ Missing entrypoint caught by linter\n";

@unlink($tempDir . '/composer.json');
@rmdir($tempDir);

echo "LinterTest passed successfully! ✅\n";
