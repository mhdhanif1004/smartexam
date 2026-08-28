<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom untuk deteksi & penandaan sesi ujian yang macet
     * (stuck) oleh command `sessions:cleanup-stuck`:
     *
     * - last_activity_at : detak jantung terakhir dari polling status() /
     *   saveAnswer(). Dipakai sebagai basis utama penentuan "stuck".
     * - timed_out_at      : jejak waktu saat sesi di-cleanup menjadi
     *   status `timed_out`.
     *
     * Status enum diperluas dengan `timed_out` untuk membedakan pengumpulan
     * otomatis (cleanup) dari pengumpulan manual siswa.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dateTime('last_activity_at')->nullable()->after('started_at');
            $table->dateTime('timed_out_at')->nullable()->after('finished_at');
        });

        DB::statement("ALTER TABLE exam_sessions MODIFY status ENUM('not_started','in_progress','completed','timed_out') NOT NULL DEFAULT 'not_started'");

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index(['status', 'last_activity_at'], 'exam_sessions_status_last_activity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex('exam_sessions_status_last_activity_index');
        });

        DB::statement("ALTER TABLE exam_sessions MODIFY status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started'");

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'timed_out_at']);
        });
    }
};
