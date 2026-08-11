<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan header & footer kartu login (satu baris aktif untuk seluruh kartu).
     * Logo tersimpan di disk local (storage/app/private/card-settings).
     */
    public function up(): void
    {
        Schema::create('card_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo_kiri_path')->nullable();
            $table->string('logo_kanan_path')->nullable();
            $table->string('nama_sekolah')->default('SmartExam');
            $table->string('nama_kepala_sekolah')->nullable();
            $table->string('jabatan_kepala_sekolah')->default('Kepala Sekolah');
            $table->string('tempat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_settings');
    }
};
