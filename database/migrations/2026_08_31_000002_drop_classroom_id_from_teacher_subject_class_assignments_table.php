<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akses guru mapel kini diturunkan dari soal yang dibuat guru (kelas
     * target lewat pivot question_classroom), bukan lagi disimpan eksplisit
     * per kombinasi mapel-kelas. Pivot cukup menyimpan mapel yang diampu;
     * kolom classroom_id (beserta data existing-nya) dihapus.
     *
     * Urutan ALTER dibuat terpisah karena MySQL menolak DROP FOREIGN KEY dan
     * DROP INDEX dalam satu statement: indeks unik lama juga menopang FK
     * guru_mapel_id, jadi indeks pengganti (guru_mapel_id, subject_id) harus
     * dibuat lebih dulu.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
        });

        // Kolom classroom_id bukan lagi sumber kebenaran; baris yang duplikat
        // per (guru, mapel) dirapikan menyisakan satu baris (id terkecil).
        DB::table('teacher_subject_class_assignments')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->guru_mapel_id.'-'.$row->subject_id)
            ->each(function ($group) {
                $group->skip(1)->each(fn ($dup) => DB::table('teacher_subject_class_assignments')->where('id', $dup->id)->delete());
            });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->unique(['guru_mapel_id', 'subject_id'], 'tsca_guru_subject_unique');
        });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropUnique('tsca_guru_subject_class_unique');
        });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->index(['subject_id'], 'tsca_subject_index');
        });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropIndex('tsca_subject_class_index');
        });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropColumn('classroom_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations: kembalikan classroom_id dan indeks lama.
     */
    public function down(): void
    {
        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropUnique('tsca_guru_subject_unique');
        });

        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->dropIndex('tsca_subject_index');
            $table->foreignId('classroom_id')->nullable()->after('subject_id')->constrained('classes')->cascadeOnDelete();
            $table->unique(['guru_mapel_id', 'subject_id', 'classroom_id'], 'tsca_guru_subject_class_unique');
            $table->index(['subject_id', 'classroom_id'], 'tsca_subject_class_index');
        });
    }
};
