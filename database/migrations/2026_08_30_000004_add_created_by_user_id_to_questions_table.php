<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom penanda pembuat soal. Soal yang dibuat guru mapel diisi dengan
     * user_id guru tersebut sehingga hanya ia yang bisa mengelola soal itu.
     * Soal lama/buatan admin tetap NULL, tidak diubah.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
