<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attitude_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('wali_kelas_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attitude_aspect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'classroom_id', 'attitude_aspect_id', 'semester_id'],
                'attitude_grades_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attitude_grades');
    }
};
