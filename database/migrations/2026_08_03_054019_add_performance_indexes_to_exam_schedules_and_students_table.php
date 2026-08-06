<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - exam_schedules(class_name, exam_date): dipakai dashboard peserta
     *   (600 siswa cek jadwal hari itu secara bersamaan) dan resolve()
     *   pada alur token/kerja/submit.
     * - students(class_name): dipakai pengawas saat mengambil daftar
     *   peserta per kelas dan filter report.
     */
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->index(['class_name', 'exam_date']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index('class_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropIndex(['class_name', 'exam_date']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['class_name']);
        });
    }
};
