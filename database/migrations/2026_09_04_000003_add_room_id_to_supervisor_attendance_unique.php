<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perbaiki unique constraint supervisor_attendances agar memperbolehkan
     * pengawas yang sama bertugas di ruangan berbeda pada jadwal yang sama.
     *
     * Sebelumnya: unique [supervisor_id, exam_schedule_id] — bentrok jika
     * pengawas ganti ruangan di periode/jadwal yang sama (duplicate entry).
     * Sesudah: unique [supervisor_id, exam_schedule_id, room_id] — cegah
     * duplikasi hanya untuk kombinasi pengawas + jadwal + ruangan yang sama.
     *
     * Catatan MySQL 1553: FK supervisor_id membutuhkan index yang diawali
     * kolom supervisor_id. Unique lama adalah satu-satunya index yang
     * meng-cover supervisor_id, jadi harus buat unique baru DULU baru hapus
     * yang lama — kalau dibalik akan error "needed in a foreign key constraint".
     */
    public function up(): void
    {
        // 1) Buat unique baru dulu agar FK tetap punya index.
        try {
            Schema::table('supervisor_attendances', function (Blueprint $table) {
                $table->unique(
                    ['supervisor_id', 'exam_schedule_id', 'room_id'],
                    'supervisor_attendances_supervisor_schedule_room_unique'
                );
            });
        } catch (Throwable $e) {
            // Abaikan jika index sudah ada (idempoten — aman di-run dua kali).
        }

        // 2) Baru hapus unique lama.
        try {
            Schema::table('supervisor_attendances', function (Blueprint $table) {
                $table->dropUnique('supervisor_attendances_supervisor_id_exam_schedule_id_unique');
            });
        } catch (Throwable $e) {
            // Abaikan jika index sudah tidak ada.
        }
    }

    /**
     * Kembalikan unique constraint ke kondisi semula.
     */
    public function down(): void
    {
        // 1) Kembalikan unique lama dulu agar FK tetap punya index sebelum yang baru dihapus.
        try {
            Schema::table('supervisor_attendances', function (Blueprint $table) {
                $table->unique(
                    ['supervisor_id', 'exam_schedule_id'],
                    'supervisor_attendances_supervisor_id_exam_schedule_id_unique'
                );
            });
        } catch (Throwable $e) {
            // Abaikan jika index sudah ada.
        }

        // 2) Baru hapus unique baru.
        try {
            Schema::table('supervisor_attendances', function (Blueprint $table) {
                $table->dropUnique('supervisor_attendances_supervisor_schedule_room_unique');
            });
        } catch (Throwable $e) {
            // Abaikan jika index sudah tidak ada.
        }
    }
};
