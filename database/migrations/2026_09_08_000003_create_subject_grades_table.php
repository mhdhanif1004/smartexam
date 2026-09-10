<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guru_mapel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();

            $table->string('title');

            $table->foreignId('exam_schedule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->enum('source', ['manual', 'cbt'])->default('manual');
            $table->boolean('is_override')->default(false);
            $table->date('taken_at')->nullable();
            $table->timestamps();

            // title SEMANTIS tetap wajib (string NOT NULL), jadi tidak ada
            // baris sistem dengan title NULL yang lolos dari unique constraint.
            // Baris auto-generated memakai title konstan:
            //   kehadiran -> 'Kehadiran'
            //   UTS 1x/semester -> 'UTS'
            //   UAS 1x/semester -> 'UAS'
            $table->unique(
                ['student_id', 'classroom_id', 'subject_id', 'exam_type_id', 'semester_id', 'title'],
                'subject_grades_manual_unique'
            );

            $table->unique(['student_id', 'exam_schedule_id'], 'subject_grades_cbt_unique');

            $table->index(['guru_mapel_id', 'subject_id', 'classroom_id'], 'subject_grades_guru_idx');
            $table->index(['student_id', 'subject_id', 'semester_id', 'exam_type_id'], 'subject_grades_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_grades');
    }
};
