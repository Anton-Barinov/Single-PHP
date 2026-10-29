<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Config;
use SinglePhp\Packager;

require_once __DIR__ . '/../src/autoload.php';

echo "Running PackagerTest...\n";

// 1. Test standard packaging
$exampleDir = __DIR__ . '/../examples/02_composer_console';
$config = Config::fromDir($exampleDir);
$packager = new Packager($config);
$summary = $packager->package();

assert(file_exists($summary['outputPath']), 'Compiled binary should exist');
assert($summary['filesCount'] === 3, 'Should bundle 3 files');
assert($summary['binarySize'] > 1000, 'Binary size should be > 1000 bytes');
echo "  ✓ Packager produced valid binary\n";

// 2. Test executing compiled binary
$output = shell_exec(escapeshellcmd($summary['outputPath']) . ' add 20 22');
assert(str_contains((string)$output, '42'), "Output should contain 42, got: {$output}");
echo "  ✓ Compiled binary executed successfully\n";

// 3. Test Phar engine packaging
$pharConfig = new Config($exampleDir, [
    'engine' => 'phar',
    'output' => 'dist/test-phar.phar'
]);
$pharPackager = new Packager($pharConfig);
$pharSummary = $pharPackager->package();

assert(file_exists($pharSummary['outputPath']), 'Phar binary should exist');
$pharOutput = shell_exec(escapeshellcmd($pharSummary['outputPath']) . ' mul 7 6');
assert(str_contains((string)$pharOutput, '42'), "Phar output should contain 42, got: {$pharOutput}");
echo "  ✓ Phar engine compilation and execution passed\n";

echo "PackagerTest passed successfully! ✅\n";
