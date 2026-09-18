<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus baris penugasan guru×mapel dengan classroom_id NULL.
     *
     * Data ini adalah data tidak valid peninggalan bug form assignment lama:
     * setSubjectClassrooms() dulu membuat baris wildcard (classroom_id null)
     * saat admin tidak memilih kelas. Keputusan final: classroom_id WAJIB
     * terisi — setiap penugasan guru×mapel harus eksplisit ke satu kelas.
     *
     * Migration ini HANYA menghapus data, tidak mengubah skema. Harus
     * dijalankan SEBELUM migration NOT NULL constraint agar kolom bisa
     * diubah menjadi NOT NULL tanpa error.
     */
    public function up(): void
    {
        DB::table('teacher_subject_class_assignments')
            ->whereNull('classroom_id')
            ->delete();
    }

    /**
     * Reverse: baris yang dihapus tidak dapat dipulihkan.
     */
    public function down(): void
    {
        // Data tidak dapat dikembalikan; tidak ada aksi reverse.
    }
};