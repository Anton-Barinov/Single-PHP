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

echo "ConfigTest passed successfully! ✅\n";
