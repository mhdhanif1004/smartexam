<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambahkan role baru `kepala_sekolah` ke kolom role (MySQL ENUM) pada tabel
     * users. Penambahan nilai enum di akhir daftar mendukung ALGORITHM=INSTANT
     * di MySQL 8, namun tetap jalankan backup database sebelum migrate sebagai
     * SOP rutin.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','pengawas','peserta','guru_mapel','kepala_sekolah') NOT NULL DEFAULT 'peserta'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','pengawas','peserta','guru_mapel') NOT NULL DEFAULT 'peserta'");
    }
};
