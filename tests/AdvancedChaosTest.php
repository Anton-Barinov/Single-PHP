<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Config;
use SinglePhp\Packager;
use SinglePhp\Bundler\ProjectScanner;

require_once __DIR__ . '/../src/autoload.php';

echo "\n=== Running Single-PHP Advanced Chaos & Production Edge Tests ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg): void
{
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$msg}\n";
    }
}

$tmpBase = sys_get_temp_dir() . '/single_php_advanced_chaos_' . uniqid();
mkdir($tmpBase, 0755, true);

try {
    // -------------------------------------------------------------
    // Test 1: Exit Code Forwarding
    // -------------------------------------------------------------
    echo "1. Testing Exit Code Forwarding from Compiled Binaries...\n";

    // Subtest A: Entrypoint returning integer exit code 42
    $projRet = $tmpBase . '/proj_exit_return';
    mkdir($projRet, 0755, true);
    file_put_contents($projRet . '/app.php', "<?php\necho \"RETURNING_42\n\";\nreturn 42;\n");
    $binRet = $projRet . '/bin_return';

    $configRet = new Config($projRet, [
        'name' => 'test-exit-code-return',
        'entrypoint' => 'app.php',
        'output' => $binRet,
        'engine' => 'phar',
    ]);
    $packagerRet = new Packager($configRet);
    $packagerRet->build();

    $outputRet = [];
    $exitCodeRet = -1;
    exec(escapeshellarg($binRet), $outputRet, $exitCodeRet);
    assertCondition($exitCodeRet === 42, "Returned integer exit code is forwarded to shell (expected 42, got {$exitCodeRet})");

    // Subtest B: Entrypoint calling explicit exit(77)
    $projExit = $tmpBase . '/proj_exit_explicit';
    mkdir($projExit, 0755, true);
    file_put_contents($projExit . '/app.php', "<?php\necho \"EXITING_77\n\";\nexit(77);\n");
    $binExit = $projExit . '/bin_explicit';

    $configExit = new Config($projExit, [
        'name' => 'test-exit-code-explicit',
        'entrypoint' => 'app.php',
        'output' => $binExit,
        'engine' => 'phar',
    ]);
    $packagerExit = new Packager($configExit);
    $packagerExit->build();

    $outputExit = [];
    $exitCodeExit = -1;
    exec(escapeshellarg($binExit), $outputExit, $exitCodeExit);
    assertCondition($exitCodeExit === 77, "Explicit exit(77) call is forwarded to shell (expected 77, got {$exitCodeExit})");

    // -------------------------------------------------------------
    // Test 2: Shebang Unix LF Line-Ending Normalization
    // -------------------------------------------------------------
    echo "\n2. Testing Shebang LF Line-Ending Cross-Platform Normalization...\n";

    $binContent = file_get_contents($binRet);
    $firstLine = strtok($binContent, "\n");

    assertCondition($firstLine === '#!/usr/bin/env php', "Shebang starts exactly with '#!/usr/bin/env php'");
    assertCondition(!str_ends_with($firstLine, "\r"), "Shebang does NOT contain carriage return (\\r) which breaks on Linux");

    // Check stub does not contain CRLF
    $stubEndPos = strpos($binContent, '__HALT_COMPILER();');
    $stubText = substr($binContent, 0, $stubEndPos !== false ? $stubEndPos : 500);
    assertCondition(!str_contains($stubText, "\r\n"), "Phar bootstrap stub uses strictly Unix LF (\\n) line endings");

    // -------------------------------------------------------------
    // Test 3: Sensitive & Garbage File Exclusions
    // -------------------------------------------------------------
    echo "\n3. Testing Default Exclusion of Sensitive Files & Secrets...\n";

    $projSec = $tmpBase . '/proj_secrets';
    mkdir($projSec, 0755, true);
    mkdir($projSec . '/.git', 0755, true);
    file_put_contents($projSec . '/.git/config', "core.bare=false\n");
    file_put_contents($projSec . '/app.php', "<?php echo 'ok';\n");
    file_put_contents($projSec . '/.env', "DB_PASSWORD=super_secret_production_password\n");
    file_put_contents($projSec . '/.env.local', "API_KEY=secret_local_key\n");
    file_put_contents($projSec . '/server.pem', "-----BEGIN CERTIFICATE-----\nSECRET\n-----END CERTIFICATE-----\n");
    file_put_contents($projSec . '/ssl.key', "-----BEGIN RSA PRIVATE KEY-----\nKEY\n-----END RSA PRIVATE KEY-----\n");
    file_put_contents($projSec . '/id_rsa', "ssh-rsa AAAAB3NzaC1yc2EAAAADAQAB...\n");
    file_put_contents($projSec . '/.DS_Store', "Mac Finder metadata bytes");
    file_put_contents($projSec . '/Thumbs.db', "Windows Explorer cache bytes");
    file_put_contents($projSec . '/composer.json', '{"name": "test/secrets"}');

    // Default configuration scanner
    $configSec = new Config($projSec, ['entrypoint' => 'app.php']);
    $scannerSec = new ProjectScanner($projSec, $configSec->getExclude(), $configSec->getInclude());
    $scannedFiles = $scannerSec->scan();

    assertCondition(isset($scannedFiles['app.php']), "Entrypoint app.php is scanned");
    assertCondition(isset($scannedFiles['composer.json']), "composer.json is scanned");
    assertCondition(!isset($scannedFiles['.env']), "Sensitive .env is excluded by default");
    assertCondition(!isset($scannedFiles['.env.local']), "Sensitive .env.local is excluded by default");
    assertCondition(!isset($scannedFiles['server.pem']), "Sensitive .pem certificate is excluded by default");
    assertCondition(!isset($scannedFiles['ssl.key']), "Sensitive .key private key is excluded by default");
    assertCondition(!isset($scannedFiles['id_rsa']), "Sensitive SSH private key id_rsa is excluded by default");
    assertCondition(!isset($scannedFiles['.DS_Store']), "OS garbage file .DS_Store is excluded by default");
    assertCondition(!isset($scannedFiles['Thumbs.db']), "OS garbage file Thumbs.db is excluded by default");
    assertCondition(!isset($scannedFiles['.git/config']), "Repository metadata .git is excluded by default");

    // Verify explicit include overrides default exclude
    $configInclude = new Config($projSec, [
        'entrypoint' => 'app.php',
        'include' => ['.env']
    ]);
    $scannerInclude = new ProjectScanner($projSec, $configInclude->getExclude(), $configInclude->getInclude());
    $scannedWithInclude = $scannerInclude->scan();
    assertCondition(isset($scannedWithInclude['.env']), "Explicitly included .env is retained when requested by developer");

    // -------------------------------------------------------------
    // Test 4: Runtime Constants & Path Resolution in Binary
    // -------------------------------------------------------------
    echo "\n4. Testing Runtime Constants & Path Resolution...\n";

    $projConst = $tmpBase . '/proj_constants';
    mkdir($projConst, 0755, true);
    file_put_contents($projConst . '/app.php', <<<'APP'
<?php
$isSingle = defined('SINGLE_PHP_RUNNING') && SINGLE_PHP_RUNNING === true ? 'YES' : 'NO';
$hasRoot = defined('SINGLE_PHP_APP_ROOT') && str_starts_with(SINGLE_PHP_APP_ROOT, 'phar://') ? 'YES' : 'NO';
$hasAlias = defined('SINGLE_PHP_PHAR_ALIAS') && !empty(SINGLE_PHP_PHAR_ALIAS) ? 'YES' : 'NO';
echo "SINGLE_PHP:{$isSingle};ROOT:{$hasRoot};ALIAS:{$hasAlias}\n";
APP
    );
    $binConst = $projConst . '/bin_const';
    $configConst = new Config($projConst, [
        'name' => 'test-constants',
        'entrypoint' => 'app.php',
        'output' => $binConst,
        'engine' => 'phar',
    ]);
    $packagerConst = new Packager($configConst);
    $packagerConst->build();

    $outputConst = [];
    $exitCodeConst = -1;
    exec(escapeshellarg($binConst), $outputConst, $exitCodeConst);
    $constResult = implode("\n", $outputConst);

    assertCondition(str_contains($constResult, 'SINGLE_PHP:YES'), "SINGLE_PHP_RUNNING is defined and true");
    assertCondition(str_contains($constResult, 'ROOT:YES'), "SINGLE_PHP_APP_ROOT starts with phar://");
    assertCondition(str_contains($constResult, 'ALIAS:YES'), "SINGLE_PHP_PHAR_ALIAS is defined");

} finally {
    // Clean up temporary files
    $cleanDir = function (string $dir) use (&$cleanDir): void {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) && !is_link($p) ? $cleanDir($p) : @unlink($p);
        }
        @rmdir($dir);
    };
    $cleanDir($tmpBase);
}

echo "\n-------------------------------------------------------\n";
echo "Results: {$passCount} Passed, {$failCount} Failed\n";
echo "-------------------------------------------------------\n";

if ($failCount > 0) {
    echo "ADVANCED CHAOS TESTS FAILED! ❌\n";
    exit(1);
}

echo "ALL ADVANCED CHAOS TESTS PASSED! 🎉\n";
