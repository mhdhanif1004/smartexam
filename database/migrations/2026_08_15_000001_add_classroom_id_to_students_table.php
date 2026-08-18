<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah relasi FK siswa -> kelas. Kolom classroom_id dibuat nullable
     * dulu, diisi dari class_name siswa yang cocok dengan master classes,
     * lalu (di migration berikutnya, SETELAH verifikasi tidak ada NULL)
     * diubah menjadi NOT NULL.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('classroom_id')
                ->nullable()
                ->after('class_name')
                ->constrained('classes')
                ->nullOnDelete();

            $table->index('classroom_id');
        });

        DB::statement(
            'UPDATE students s JOIN classes c ON c.name = s.class_name SET s.classroom_id = c.id'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
