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
     * Migrasi aman/idempoten: tidak menghapus data, tidak mengubah kolom lain,
     * dibungkus try/catch agar tidak error jika di-run dua kali.
     */
    public function up(): void
    {
        // Hapus unique lama [supervisor_id, exam_schedule_id] jika ada.
        // Nama index default Laravel: supervisor_attendances_supervisor_id_exam_schedule_id_unique
        Schema::table('supervisor_attendances', function (Blueprint $table) {
            try {
                $table->dropUnique(['supervisor_id', 'exam_schedule_id']);
            } catch (\Throwable $e) {
                // Abaikan jika index sudah tidak ada (idempoten / sudah di-migrate sebelumnya).
            }
        });

        // Buat unique baru [supervisor_id, exam_schedule_id, room_id].
        Schema::table('supervisor_attendances', function (Blueprint $table) {
            try {
                $table->unique(
                    ['supervisor_id', 'exam_schedule_id', 'room_id'],
                    'supervisor_attendances_supervisor_schedule_room_unique'
                );
            } catch (\Throwable $e) {
                // Abaikan jika index sudah ada (idempoten — aman di-run dua kali).
            }
        });
    }

    /**
     * Kembalikan unique constraint ke kondisi semula.
     */
    public function down(): void
    {
        // Hapus unique baru [supervisor_id, exam_schedule_id, room_id] jika ada.
        Schema::table('supervisor_attendances', function (Blueprint $table) {
            try {
                $table->dropUnique('supervisor_attendances_supervisor_schedule_room_unique');
            } catch (\Throwable $e) {
                // Abaikan jika index sudah tidak ada.
            }
        });

        // Kembalikan unique lama [supervisor_id, exam_schedule_id].
        Schema::table('supervisor_attendances', function (Blueprint $table) {
            try {
                $table->unique(
                    ['supervisor_id', 'exam_schedule_id'],
                    'supervisor_attendances_supervisor_id_exam_schedule_id_unique'
                );
            } catch (\Throwable $e) {
                // Abaikan jika index sudah ada.
            }
        });
    }
};
