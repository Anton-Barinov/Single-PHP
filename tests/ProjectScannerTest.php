<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Bundler\ProjectScanner;

require_once __DIR__ . '/../src/autoload.php';

echo "Running ProjectScannerTest...\n";

$exampleDir = __DIR__ . '/../examples/02_composer_console';
$scanner = new ProjectScanner($exampleDir);
$files = $scanner->scan();

assert(isset($files['bin/calc']), 'bin/calc should be discovered');
assert(isset($files['src/Calculator.php']), 'src/Calculator.php should be discovered');
assert(isset($files['composer.json']), 'composer.json should be discovered');
echo "  ✓ Discovered all project files\n";

// Test exclude rules
$scannerWithExclude = new ProjectScanner($exampleDir, ['bin/*']);
$filtered = $scannerWithExclude->scan();
assert(!isset($filtered['bin/calc']), 'bin/calc should be excluded');
assert(isset($filtered['src/Calculator.php']), 'src/Calculator.php should be kept');
echo "  ✓ Pattern exclusions respected\n";

echo "ProjectScannerTest passed successfully! ✅\n";
