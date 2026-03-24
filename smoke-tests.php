<?php
$files = [
    'config.php',
    'index.php',
    'login.php',
    'layout.php',
    'dashboard.php',
    'projetos.php',
];

$base = __DIR__ . DIRECTORY_SEPARATOR;
$failed = 0;
$results = [];

foreach ($files as $file) {
    $path = realpath($base . $file);
    if (!$path) {
        $results[$file] = 'MISSING';
        $failed++;
        continue;
    }
    try {
        ob_start();
        include $path;
        ob_end_clean();
        $results[$file] = 'OK';
    } catch (Throwable $e) {
        $results[$file] = 'ERROR: ' . $e->getMessage();
        $failed++;
    }
}

foreach ($results as $f => $r) {
    echo $f . ': ' . $r . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
