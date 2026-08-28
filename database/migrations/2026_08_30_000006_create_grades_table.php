<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai manual yang diinput guru mapel untuk Kegiatan Belajar Mengajar.
     * Terpisah total dari alur auto-grading CBT (exam_results) — dibuat
     * supaya guru bisa mencatat nilai tugas/harian/UTS/UAS lainnya tanpa
     * menyentuh alur ujian yang sudah ada.
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mapel_id')->constrained('guru_mapels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('grade_type', ['tugas', 'harian', 'uts', 'uas', 'lainnya'])->default('tugas');
            $table->string('title')->nullable();
            $table->decimal('score', 5, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'subject_id'], 'grades_student_subject_idx');
            $table->index('grade_type', 'grades_type_idx');
            $table->index('classroom_id', 'grades_classroom_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
