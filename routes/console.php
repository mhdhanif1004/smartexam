<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exam-schedules:sync-status')->everyMinute()->withoutOverlapping(10);

Schedule::command('tokens:rotate')->everyMinute()->withoutOverlapping(10);

Schedule::command('sessions:cleanup-stuck')->everyFiveMinutes()->withoutOverlapping(20);
