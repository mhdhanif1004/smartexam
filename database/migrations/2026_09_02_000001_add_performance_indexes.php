<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // exam_schedules: filter utama sync-status & dashboard
        Schema::table('exam_schedules', function (Blueprint $table) {
            $this->addIndexIfMissing($table, ['exam_date'], 'exam_schedules_exam_date_index');
            $this->addIndexIfMissing($table, ['status'], 'exam_schedules_status_index');
            $this->addIndexIfMissing($table, ['exam_period_id'], 'exam_schedules_exam_period_id_index');
            $this->addIndexIfMissing($table, ['room_id'], 'exam_schedules_room_id_index');
            $this->addIndexIfMissing($table, ['subject_id'], 'exam_schedules_subject_id_index');
        });

        // violations: polling pengawas & badge
        Schema::table('violations', function (Blueprint $table) {
            $this->addIndexIfMissing($table, ['handled_at'], 'violations_handled_at_index');
            $this->addIndexIfMissing($table, ['exam_session_id', 'handled_at'], 'violations_session_handled_index');
            $this->addIndexIfMissing($table, ['violation_type'], 'violations_violation_type_index');
            $this->addIndexIfMissing($table, ['occurred_at'], 'violations_occurred_at_index');
        });

        // jobs: queue polling
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                $this->addIndexIfMissing($table, ['reserved_at'], 'jobs_reserved_at_index');
                $this->addIndexIfMissing($table, ['available_at'], 'jobs_available_at_index');
            });
        }

        // cache: expiration GC
        if (Schema::hasTable('cache')) {
            Schema::table('cache', function (Blueprint $table) {
                $this->addIndexIfMissing($table, ['expiration'], 'cache_expiration_index');
            });
        }
        if (Schema::hasTable('cache_locks')) {
            Schema::table('cache_locks', function (Blueprint $table) {
                $this->addIndexIfMissing($table, ['expiration'], 'cache_locks_expiration_index');
            });
        }

        // exam_room_assignments: lookup by room
        if (Schema::hasTable('exam_room_assignments')) {
            Schema::table('exam_room_assignments', function (Blueprint $table) {
                $this->addIndexIfMissing($table, ['room_id'], 'exam_room_assignments_room_id_index');
                $this->addIndexIfMissing($table, ['exam_period_id'], 'exam_room_assignments_period_id_index');
            });
        }
    }

    public function down(): void
    {
        // tidak hapus index saat rollback agar aman
    }

    private function addIndexIfMissing(Blueprint $table, array $columns, string $name): void
    {
        try {
            $table->index($columns, $name);
        } catch (\Throwable $e) {
            // index sudah ada — lewati
        }
    }
};
