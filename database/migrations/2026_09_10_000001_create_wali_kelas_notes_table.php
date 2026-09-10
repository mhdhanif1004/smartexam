<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan wali kelas untuk siswa (append-only / buku penghubung).
     *
     * Setiap baris adalah satu entri observasi/kejadian yang ditulis wali
     * kelas — bukan overwrite. Riwayat catatan siswa harus tetap ada
     * meskipun akun wali kelas dihapus (nullOnDelete pada wali_kelas_id).
     */
    public function up(): void
    {
        Schema::create('wali_kelas_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('wali_kelas_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('tipe', 50)->nullable(); // observasi|pelanggaran|prestasi|lainnya
            $table->text('catatan');
            $table->timestamps();

            $table->index(['student_id', 'classroom_id', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wali_kelas_notes');
    }
};
