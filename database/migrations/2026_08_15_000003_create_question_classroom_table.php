<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot many-to-many Question <-> Classroom. Satu soal menentukan sendiri
     * kelas mana yang berhak menerimanya. Tidak ada kolom "tingkat" — sumber
     * kebenaran tunggal adalah baris-baris individual di tabel ini.
     */
    public function up(): void
    {
        Schema::create('question_classroom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'classroom_id']);
            $table->index('classroom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_classroom');
    }
};
