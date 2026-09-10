<?php

use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/layouts/guru_mapel.blade.php'));
    echo "layouts.guru_mapel COMPILED OK\n";
} catch (Throwable $e) {
    echo 'ERROR in layouts.guru_mapel: '.$e->getMessage()."\n";
    echo 'FILE: '.$e->getFile().':'.$e->getLine()."\n";
}
