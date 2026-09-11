<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Semua baris semester yang ada adalah data dummy/seed dari era
        // lama (kolom year + semester digabung). Dihapus bersih supaya
        // admin mulai dari kosong — struktur baru pakai academic_year_id
        // + jenis (hierarki 2 level: Tahun Ajaran -> Semester).
        DB::table('semesters')->delete();

        Schema::table('semesters', function (Blueprint $table) {
            // Relasi ke tahun ajaran. restrictOnDelete: tahun ajaran yang
            // masih punya semester TIDAK boleh dihapus (cegah hapus diam-diam).
            $table->foreignId('academic_year_id')
                ->after('id')
                ->constrained('academic_years')
                ->restrictOnDelete();

            $table->string('jenis')->after('academic_year_id'); // 'ganjil' | 'genap'

            // Kolom lama (single-row dummy) tidak dipakai lagi.
            $table->dropColumn(['year', 'semester']);

            // Tidak boleh ada 2 semester "Ganjil" dalam 1 tahun ajaran yang sama.
            $table->unique(['academic_year_id', 'jenis'], 'semesters_academic_year_jenis_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropUnique('semesters_academic_year_jenis_unique');
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'jenis']);

            $table->string('year')->after('id');
            $table->unsignedTinyInteger('semester')->after('year');
        });
    }
};
