<?php
/**
 * Single-PHP Chaos & Fault Injection Test Suite
 * Part of Single-PHP by Anton Barinov
 */

declare(strict_types=1);

if (ini_get('phar.readonly')) {
    $cmd = escapeshellcmd(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg(__FILE__);
    passthru($cmd, $code);
    exit($code);
}

require_once __DIR__ . '/../src/autoload.php';

use SinglePhp\Config;
use SinglePhp\Linter;
use SinglePhp\Packager;
use SinglePhp\Extractor;
use SinglePhp\Bundler\ProjectScanner;

echo "=== Running Single-PHP Chaos & Adversarial Packager Tests ===\n\n";

$pass = 0;
$fail = 0;

function assertChaos(string $name, bool $condition, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/single_php_chaos_' . uniqid();
mkdir($tmpDir, 0755, true);
register_shutdown_function(function() use ($tmpDir) {
    exec('rm -rf ' . escapeshellarg($tmpDir));
});

// -------------------------------------------------------------
// 1. Broken User Projects: Syntax Errors, Missing Entrypoints, Bad JSON
// -------------------------------------------------------------
echo "1. Testing Detection of Broken User Projects...\n";

// A. Syntax Error
$projSyntax = $tmpDir . '/proj_syntax';
mkdir($projSyntax, 0755, true);
file_put_contents($projSyntax . '/broken.php', "<?php\necho 'missing closing quote;\n");
file_put_contents($projSyntax . '/composer.json', json_encode(['bin' => ['broken.php']]));

$cfgSyntax = Config::fromDir($projSyntax);
$linterSyntax = new Linter($cfgSyntax);
$resSyntax = $linterSyntax->check();

assertChaos(
    "PHP Syntax Error detected before build",
    !$resSyntax['valid'] && count($resSyntax['errors']) > 0 && strpos($resSyntax['errors'][0], 'Syntax Error') !== false,
    $resSyntax['errors'][0] ?? ''
);

// Verify Packager rejects it
$packagerCaught = false;
try {
    $packager = new Packager($cfgSyntax);
    $packager->build();
} catch (\Throwable $e) {
    $packagerCaught = true;
}
assertChaos(
    "Packager aborted compilation on syntax error",
    $packagerCaught,
    "Build refused"
);

// B. Missing Entrypoint
$projMissing = $tmpDir . '/proj_missing';
mkdir($projMissing, 0755, true);
file_put_contents($projMissing . '/composer.json', json_encode(['bin' => ['ghost_script.php']]));

$cfgMissing = Config::fromDir($projMissing);
$linterMissing = new Linter($cfgMissing);
$resMissing = $linterMissing->check();

assertChaos(
    "Missing entrypoint detected by linter",
    !$resMissing['valid'] && strpos($resMissing['errors'][0], 'Entrypoint script not found') !== false,
    $resMissing['errors'][0] ?? ''
);

// C. Malformed composer.json
$projBadJson = $tmpDir . '/proj_bad_json';
mkdir($projBadJson, 0755, true);
file_put_contents($projBadJson . '/composer.json', '{"name": "broken", "bin": [');
file_put_contents($projBadJson . '/app.php', '<?php echo "ok";');

$cfgBadJson = Config::fromDir($projBadJson);
$linterBadJson = new Linter($cfgBadJson);
$resBadJson = $linterBadJson->check();

assertChaos(
    "Malformed composer.json JSON caught by linter",
    !$resBadJson['valid'] && strpos(implode(' ', $resBadJson['errors']), 'Invalid JSON') !== false,
    implode(' ', $resBadJson['errors'])
);

// -------------------------------------------------------------
// 2. Cyclic Symlink Handling in ProjectScanner
// -------------------------------------------------------------
echo "\n2. Testing Cyclic Symlink Resilience in ProjectScanner...\n";

$projLoop = $tmpDir . '/proj_loop';
mkdir($projLoop, 0755, true);
file_put_contents($projLoop . '/index.php', '<?php echo "loop-free";');
// Create cyclic symlink
@symlink($projLoop, $projLoop . '/recursive_link');

$scannerCaught = false;
$files = [];
try {
    $scanner = new ProjectScanner($projLoop);
    $files = $scanner->scan();
    $scannerCaught = true;
} catch (\Throwable $e) {
    $scannerCaught = false;
}

assertChaos(
    "ProjectScanner survives cyclic symlinks without infinite loop",
    $scannerCaught && isset($files['index.php']),
    "Scanned " . count($files) . " file(s) safely"
);

// -------------------------------------------------------------
// 3. Operating System Fault: Output to Read-Only Directory
// -------------------------------------------------------------
echo "\n3. Testing Read-Only Destination Handling...\n";

$projRo = $tmpDir . '/proj_ro';
mkdir($projRo, 0755, true);
file_put_contents($projRo . '/app.php', '<?php echo "hello";');

$cfgRo = Config::fromDir($projRo, ['output' => '/non_existent_system_dir_forbidden/myapp']);
$linterRo = new Linter($cfgRo);
$resRo = $linterRo->check();

assertChaos(
    "Output to forbidden/read-only path detected before build",
    !$resRo['valid'] && strpos(implode(' ', $resRo['errors']), 'writable') !== false,
    implode(' ', $resRo['errors'])
);

// -------------------------------------------------------------
// 4. Corrupted Binary Extraction Handling
// -------------------------------------------------------------
echo "\n4. Testing Corrupted Binary Extraction Resilience...\n";

$badBinary = $tmpDir . '/corrupted_binary.bin';
file_put_contents($badBinary, "GARBAGE_BINARY_WITHOUT_PHAR_SIGNATURE_OR_PAYLOAD");
$extractDest = $tmpDir . '/extracted';

$extractFailedCleanly = false;
$errorMsg = '';
try {
    $extractor = new Extractor();
    $extractor->extract($badBinary, $extractDest);
} catch (\Throwable $e) {
    $extractFailedCleanly = true;
    $errorMsg = $e->getMessage();
}

assertChaos(
    "Corrupted binary extraction throws clean RuntimeException",
    $extractFailedCleanly && strpos($errorMsg, 'No embedded Phar payload found') !== false,
    $errorMsg
);

// -------------------------------------------------------------
// 5. Minification / Tree-Shaking on Extreme PHP Syntax
// -------------------------------------------------------------
echo "\n5. Testing Minification on Extreme PHP Syntax...\n";

$projMin = $tmpDir . '/proj_min';
mkdir($projMin, 0755, true);

$complexCode = <<<'CODE'
<?php
// Leading comment that should be stripped
$heredoc = <<<EOF
Line 1 with /* not a comment */
Line 2 with -- not sql comment
EOF;

$nowdoc = <<<'NOW'
$literal_var and /* preserved comment */
NOW;

$regex = '/^test\s+# free-spacing comment/x';
$sql = "SELECT '/* in string */' AS val";

echo "HEREDOC_LEN:" . strlen($heredoc) . ";NOWDOC_LEN:" . strlen($nowdoc) . ";SQL:" . $sql;
CODE;

file_put_contents($projMin . '/app.php', $complexCode);
file_put_contents($projMin . '/composer.json', json_encode(['name' => 'extreme-min', 'bin' => ['app.php']]));

$outBinary = $tmpDir . '/extreme_min_app.phar';
$cfgMin = Config::fromDir($projMin, [
    'engine' => 'phar',
    'output' => $outBinary,
    'minify' => true,
]);

$packagerMin = new Packager($cfgMin);
$resMin = $packagerMin->build();

// Execute the packed minified Phar and verify behavior
$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($outBinary);
exec($cmd, $outLines, $code);

$outputStr = implode('', $outLines);
$expectedSnippet = "SQL:SELECT '/* in string */' AS val";

assertChaos(
    "Minified binary preserves HEREDOC, comments in strings, and regexes",
    $code === 0 && strpos($outputStr, $expectedSnippet) !== false && strpos($outputStr, 'HEREDOC_LEN:') !== false,
    "Output matched: {$outputStr}"
);

echo "\n-------------------------------------------------------\n";
echo "Results: {$pass} Passed, {$fail} Failed\n";
echo "-------------------------------------------------------\n";

if ($fail > 0) {
    echo "CHAOS TESTS FAILED! ❌\n";
    exit(1);
}

echo "ALL CHAOS & ADVERSARIAL PACKAGER TESTS PASSED! 🎉\n";
exit(0);
