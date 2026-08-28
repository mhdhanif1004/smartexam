<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot 3-arah: guru mapel mengajar mapel X di kelas Y. Mendukung
     * co-teaching (satu mapel-kelas bisa diampu >1 guru) karena unique
     * per-kombinasi guru+mapel+kelas, bukan per mapel+kelas.
     */
    public function up(): void
    {
        Schema::create('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mapel_id')->constrained('guru_mapels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guru_mapel_id', 'subject_id', 'classroom_id'], 'tsca_guru_subject_class_unique');
            $table->index(['subject_id', 'classroom_id'], 'tsca_subject_class_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_class_assignments');
    }
};
