<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pencatat pembuat ExamPeriod — dasar otorisasi "mode pengawas mandiri"
     * Guru Mapel (hanya boleh awasi sesi buatan sendiri).
     *
     * Nullable: ExamPeriod lama / buatan Admin (auto-generate, groups, manual)
     * TIDAK diberi nilai retrospektif — `created_by_user_id = null` berarti
     * "dibuat oleh sistem/admin lama" dan hanya guru yang menciptakan period
     * lewat fitur baru yang punya nilai.
     */
    public function up(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('exam_type_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
