<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil 1:1 wali kelas dengan akun users, mengikuti pola yang sama
     * dengan guru_mapels/kepala_sekolahs. Satu wali kelas terikat ke TEPAT
     * SATU kelas (classroom_id UNIQUE → satu kelas maksimal satu wali), dan
     * tanpa mapel (berbeda dari guru mapel yang mengampu banyak mapel-kelas).
     */
    public function up(): void
    {
        Schema::create('wali_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->unique()->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wali_kelas');
    }
};
