<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perketat kolom attendance_status menjadi ENUM agar tidak ada nilai liar.
     */
    public function up(): void
    {
        // Bersihkan data liar: nilai selain 'hadir'/'tidak_hadir' di-null-kan dulu agar ALTER ENUM tidak gagal.
        DB::table('exam_sessions')
            ->whereNotIn('attendance_status', ['hadir', 'tidak_hadir'])
            ->whereNotNull('attendance_status')
            ->update(['attendance_status' => null]);

        // Ubah kolom menjadi ENUM nullable setelah kolom status (pakai raw statement agar tidak butuh doctrine/dbal).
        DB::statement("ALTER TABLE `exam_sessions` MODIFY COLUMN `attendance_status` ENUM('hadir','tidak_hadir') NULL AFTER `status`");
    }

    /**
     * Kembalikan kolom attendance_status ke VARCHAR(255) nullable.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `exam_sessions` MODIFY COLUMN `attendance_status` VARCHAR(255) NULL AFTER `status`");
    }
};
