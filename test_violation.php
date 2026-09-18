<?php

use App\Models\Violation;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$v = new Violation(['violation_type' => 'berpindah_tab']);
echo $v->typeLabel."\n";
