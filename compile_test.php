<?php

use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/guru_mapel/questions/index.blade.php'));
    echo "COMPILED OK\n";
    echo substr($compiled, 0, 500)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'FILE: '.$e->getFile().':'.$e->getLine()."\n";
}
