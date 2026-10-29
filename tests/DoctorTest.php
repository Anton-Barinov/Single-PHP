<?php

declare(strict_types=1);

namespace SinglePhp\Tests;

use SinglePhp\Doctor;

require_once __DIR__ . '/../src/autoload.php';

echo "Running DoctorTest...\n";

$doctor = new Doctor();
$diagnostics = $doctor->diagnose();

assert(!empty($diagnostics), 'Doctor should return diagnostic checks');

$titles = array_column($diagnostics, 'title');
assert(in_array('PHP Runtime Version', $titles), 'Should check PHP Runtime Version');
assert(in_array('Phar Extension', $titles), 'Should check Phar Extension');
assert(in_array('Host Architecture', $titles), 'Should check Host Architecture');
assert(in_array('Cache Directory', $titles), 'Should check Cache Directory');

echo "  ✓ Diagnostic checks returned all core system validations\n";
echo "DoctorTest passed successfully! ✅\n";
