<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/guru_mapel/questions/partials/import-modal.blade.php'));
    echo "import-modal COMPILED OK\n";
} catch (\Throwable $e) {
    echo "ERROR in import-modal: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "TRACE:\n";
    echo $e->getTraceAsString() . "\n";
}
