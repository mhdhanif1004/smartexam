<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akses guru mapel kembali menyimpan kelas secara eksplisit per kombinasi
     * (guru, mapel, kelas), bukan lagi diturunkan dari soal yang dibuat guru.
     * Hal ini diperlukan agar fitur impor Guru Mapel dapat mempertahankan
     * penugasan per kelas (termasuk perluasan "SEMUA kelas" pada suatu tingkat).
     *
     * Kolom classroom_id dibuat nullable agar penugasan mapel-kelas bersifat
     * opsional (bisa berupa penugasan mapel saja) dan baris lama yang hanya
     * berisi (guru, mapel) tetap valid. Unik per kombinasi guru+mapel+kelas
     * dijaga lewat indeks unik 3 kolom; MySQL/MariaDB membolehkan beberapa
     * baris dengan classroom_id NULL pada indeks unik.
     *
     * Tabel dibuat ulang secara utuh (create -> copy -> swap) alih-alih ALTER
     * kolom karena pada MariaDB 10.4, ALTER yang menghapus/membuat kembali
     * indeks unik dengan kolom nullable yang menopang FK guru_mapel_id membuat
     * cek FK delete pada guru_mapels berperilaku salah (error 1451 "temp file
     * operation failed") meskipun tak ada baris anak. Membuat ulang tabel akan
     * menghasilkan indeks dan metadata FK yang bersih.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $new = 'teacher_subject_class_assignments_new_tmp';

        Schema::dropIfExists($new);
        Schema::create($new, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_mapel_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('classroom_id')->nullable();
            $table->timestamps();

            $table->foreign('guru_mapel_id')->references('id')->on('guru_mapels')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classes')->cascadeOnDelete();

            $table->unique(['guru_mapel_id', 'subject_id', 'classroom_id'], 'tsca_guru_subject_class_unique');
            $table->index(['subject_id'], 'tsca_subject_index');
            $table->index(['classroom_id'], 'tsca_classroom_index');
        });

        $cols = array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM teacher_subject_class_assignments'));
        $hasClassroom = in_array('classroom_id', $cols, true);

        $target = ['guru_mapel_id', 'subject_id', 'classroom_id', 'created_at', 'updated_at'];
        $select = $hasClassroom
            ? ['guru_mapel_id', 'subject_id', 'classroom_id', 'created_at', 'updated_at']
            : ['guru_mapel_id', 'subject_id', DB::raw('NULL'), 'created_at', 'updated_at'];

        DB::table($new)->insertUsing($target, DB::table('teacher_subject_class_assignments')->select($select));

        Schema::dropIfExists('teacher_subject_class_assignments');
        Schema::rename($new, 'teacher_subject_class_assignments');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations: kembalikan ke unik per (guru, mapel) dan tanpa
     * kolom classroom_id.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $new = 'teacher_subject_class_assignments_down_tmp';

        Schema::dropIfExists($new);
        Schema::create($new, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_mapel_id');
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->foreign('guru_mapel_id')->references('id')->on('guru_mapels')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();

            $table->unique(['guru_mapel_id', 'subject_id'], 'tsca_guru_subject_unique');
            $table->index(['subject_id'], 'tsca_subject_index');
        });

        DB::table($new)->insertUsing(
            ['guru_mapel_id', 'subject_id', 'created_at', 'updated_at'],
            DB::table('teacher_subject_class_assignments')->select([
                'guru_mapel_id', 'subject_id', 'created_at', 'updated_at',
            ])
        );

        Schema::dropIfExists('teacher_subject_class_assignments');
        Schema::rename($new, 'teacher_subject_class_assignments');

        Schema::enableForeignKeyConstraints();
    }
};
