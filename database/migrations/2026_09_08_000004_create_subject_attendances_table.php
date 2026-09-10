<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guru_mapel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_days');
            $table->unsignedSmallInteger('present_days');
            $table->unsignedSmallInteger('absent_days');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'classroom_id', 'subject_id', 'semester_id'],
                'subject_attendances_unique'
            );

            $table->index(['guru_mapel_id', 'subject_id', 'classroom_id', 'semester_id'], 'subject_attendances_guru_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_attendances');
    }
};
