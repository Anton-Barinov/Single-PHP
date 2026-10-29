<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Config;

require_once __DIR__ . '/../src/autoload.php';

echo "Running ConfigTest...\n";

// 1. Test config auto-discovery from composer.json
$exampleDir = __DIR__ . '/../examples/01_simple_cli';
$config = Config::fromDir($exampleDir);

assert($config->getName() === 'sample-hello-cli', "Name should be sample-hello-cli, got {$config->getName()}");
assert($config->getVersion() === '1.0.0', 'Version should match');
assert($config->getEntrypoint() === 'app.php', "Entrypoint should be app.php, got {$config->getEntrypoint()}");
assert($config->getEngine() === 'micro', 'Default engine should be micro');
echo "  ✓ Auto-discovery from composer.json passed\n";

// 2. Test manual overrides
$customConfig = new Config($exampleDir, [
    'name'       => 'my-custom-tool',
    'version'    => '3.0.0',
    'entrypoint' => 'custom.php',
    'engine'     => 'phar',
    'target'     => 'linux-x86_64',
]);

assert($customConfig->getName() === 'my-custom-tool');
assert($customConfig->getVersion() === '3.0.0');
assert($customConfig->getEntrypoint() === 'custom.php');
assert($customConfig->getEngine() === 'phar');
assert($customConfig->getTarget() === 'linux-x86_64');
echo "  ✓ Manual configuration overrides passed\n";

// 3. Test Windows target adds .exe suffix
$winConfig = new Config($exampleDir, [
    'target' => 'windows-x64',
    'output' => 'dist/winapp'
]);
assert(str_ends_with($winConfig->getOutput(), '.exe'), "Windows binary should end with .exe, got {$winConfig->getOutput()}");
echo "  ✓ Windows target automatically ensures .exe extension\n";

// 4. Test minify and env configuration
$envConfig = new Config($exampleDir, [
    'minify' => true,
    'env'    => ['APP_ENV' => 'production', 'DB_HOST' => '10.0.0.1'],
    'ini'    => ['memory_limit' => '1024M'],
]);
assert($envConfig->isMinify() === true, 'Minify should be enabled');
assert($envConfig->getEnv()['APP_ENV'] === 'production');
assert($envConfig->getPhpIni()['memory_limit'] === '1024M');
echo "  ✓ Minify and custom env configuration passed\n";

echo "ConfigTest passed successfully! ✅\n";
