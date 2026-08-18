<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * classroom_id sudah terisi 100% oleh migration sebelumnya (diverifikasi
     * sebelum menjalankan migration ini). Amankan menjadi NOT NULL.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Kolom sudah terisi 100%; jadikan NOT NULL. FK lama memakai
            // nullOnDelete (SET NULL) yang bertentangan dengan NOT NULL,
            // sehingga dibuang dulu lalu dibuat ulang dengan restrict
            // (penghapusan kelas diblokir bila masih ada siswanya).
            $table->dropForeign(['classroom_id']);
            $table->unsignedBigInteger('classroom_id')->nullable(false)->change();
            $table->foreign('classroom_id')->references('id')->on('classes')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->unsignedBigInteger('classroom_id')->nullable()->change();
            $table->foreign('classroom_id')->references('id')->on('classes')->nullOnDelete();
        });
    }
};
