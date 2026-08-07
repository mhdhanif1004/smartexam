<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use Illuminate\Console\Command;

class SyncExamScheduleStatus extends Command
{
    protected $signature = 'exam-schedules:sync-status';

    protected $description = 'Sinkronkan kolom status exam_schedules dengan current_status real-time';

    public function handle(): int
    {
        $updated = ExamSchedule::syncAllStatuses();

        $this->info("Status ujian disinkronkan: {$updated} jadwal diperbarui.");

        return Command::SUCCESS;
    }
}
