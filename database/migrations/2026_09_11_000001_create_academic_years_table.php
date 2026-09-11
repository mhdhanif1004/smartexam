<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique(); // e.g. "2024/2025"
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            // CATATAN PENTING: is_active di sini HANYA untuk kemudahan tampilan
            // admin ("tahun ajaran berjalan"). BUKAN sumber kebenaran sistem —
            // sumber kebenaran tetap `semesters.is_active` seperti sebelumnya.
            // Jangan gunakan kolom ini sebagai filter default di query bisnis.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
