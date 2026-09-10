<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris hasil CBT (subject_grades) bisa dibuat tanpa relasi guru mapel
     * (jadwal ujian tidak wajib punya pengampu) dan tanpa semester ter-resolve
     * pada lingkungan yang belum menyetel semester aktif. Dua kolom itu
     * dijadikan nullable agar sync dari ExamGradingService tidak crash.
     */
    public function up(): void
    {
        Schema::table('subject_grades', function (Blueprint $table) {
            $table->foreignId('guru_mapel_id')->nullable()->change();
            $table->foreignId('semester_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subject_grades', function (Blueprint $table) {
            $table->foreignId('guru_mapel_id')->nullable(false)->change();
            $table->foreignId('semester_id')->nullable(false)->change();
        });
    }
};
