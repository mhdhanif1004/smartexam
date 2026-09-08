<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check admin.partials.flash
try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/admin/partials/flash.blade.php'));
    echo "admin.partials.flash COMPILED OK\n";
} catch (\Throwable $e) {
    echo "ERROR in admin.partials.flash: " . $e->getMessage() . "\n";
}

// Check admin.partials.delete-modal
try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/admin/partials/delete-modal.blade.php'));
    echo "admin.partials.delete-modal COMPILED OK\n";
} catch (\Throwable $e) {
    echo "ERROR in admin.partials.delete-modal: " . $e->getMessage() . "\n";
}

// Check layouts.guru_mapel
try {
    $compiled = app('blade.compiler')->compileString(file_get_contents('resources/views/components/layouts/guru_mapel.blade.php'));
    echo "layouts.guru_mapel COMPILED OK\n";
} catch (\Throwable $e) {
    echo "ERROR in layouts.guru_mapel: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
