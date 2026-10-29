<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Inspector;
use SinglePhp\Config;
use SinglePhp\Packager;

require_once __DIR__ . '/../src/autoload.php';

echo "Running InspectorTest...\n";

$exampleDir = __DIR__ . '/../examples/01_simple_cli';
$config = Config::fromDir($exampleDir);
$packager = new Packager($config);
$summary = $packager->package();

$inspector = new Inspector();
$data = $inspector->inspect($summary['outputPath']);

assert($data['hasPharPayload'] === true, 'Inspector should detect Phar payload');
assert($data['manifest']['name'] === 'sample-hello-cli', 'Manifest name should match');
assert($data['manifest']['entrypoint'] === 'app.php', 'Manifest entrypoint should match');
assert(count($data['files']) === 2, 'Should inspect 2 bundled files');
echo "  ✓ Inspector accurately extracted manifest and file count\n";

echo "InspectorTest passed successfully! ✅\n";
