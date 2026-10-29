#!/usr/bin/env php
<?php

declare(strict_types=1);

$files = [
    __DIR__ . '/ConfigTest.php',
    __DIR__ . '/ProjectScannerTest.php',
    __DIR__ . '/PharBuilderTest.php',
    __DIR__ . '/InspectorTest.php',
    __DIR__ . '/PackagerTest.php',
    __DIR__ . '/DoctorTest.php',
    __DIR__ . '/LinterTest.php',
    __DIR__ . '/ChaosPackagerTest.php',
    __DIR__ . '/AdvancedChaosTest.php',
];

$failed = 0;
echo "=========================================\n";
echo " Running Single-PHP Test Suite\n";
echo "=========================================\n\n";

foreach ($files as $file) {
    $cmd = escapeshellcmd(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg($file);
    passthru($cmd, $returnVar);
    if ($returnVar !== 0) {
        $failed++;
        echo "❌ FAILED: " . basename($file) . "\n\n";
    } else {
        echo "✅ PASSED: " . basename($file) . "\n\n";
    }
}

if ($failed > 0) {
    echo "Summary: {$failed} test file(s) failed.\n";
    exit(1);
}

echo "All Single-PHP tests passed successfully! 🎉\n";
exit(0);
