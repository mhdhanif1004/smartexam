<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai mapel hanya untuk catatan skor ujian di SmartExam (skor murni per
     * mapel-kelas-siswa). grade_type (tugas/harian/uts/uas/lainnya) dan title
     * tidak lagi relevan karena fitur tidak mengakomodasi PBM/KBM harian di
     * luar sistem. Kolom dihapus total; tabel grades saat itu belum berisi
     * data (0 baris), sehingga aman tanpa migrasi data.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex('grades_type_idx');
            $table->dropColumn(['grade_type', 'title']);
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->enum('grade_type', ['tugas', 'harian', 'uts', 'uas', 'lainnya'])->default('tugas');
            $table->string('title')->nullable();
            $table->index('grade_type', 'grades_type_idx');
        });
    }
};
