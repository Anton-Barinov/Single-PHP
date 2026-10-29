<?php

declare(strict_types=1);

echo "Hello from Single-PHP Standalone Binary!\n";
echo "Arguments received: " . json_encode(array_slice($argv, 1)) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
