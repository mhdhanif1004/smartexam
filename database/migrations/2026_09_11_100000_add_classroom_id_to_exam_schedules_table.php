<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buat `room_id` nullable + tambah `classroom_id` nullable + CHECK constraint
     * exactly-one-of(room_id, classroom_id).
     *
     * CHECK ensures mutual exclusivity: jika room diisi, classroom harus null
     * (sebaliknya juga). Constraint ini melengkapi validasi di Form Request
     * sebagai pertahanan di level database.
     *
     * MariaDB 10.4+ menegakkan CHECK constraint (terverifikasi).
     */
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            // 1. Drop FK existing pada room_id supaya bisa diubah ke nullable.
            $table->dropForeign(['room_id']);

            // 2. Jadikan room_id nullable (schema saat ini: NOT NULL).
            $table->foreignId('room_id')->nullable()->change();

            // 3. Re-add FK cascadeOnDelete (konsisten dengan semula).
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();

            // 4. Kolom baru: classroom_id (nullable, FK ke classes, restrictOnDelete).
            //    restrictOnDelete: tahun ajaran/kelas yang masih punya jadwal ujian
            //    aktif tidak bisa dihapus tanpa menghapus jadwalnya lebih dulu.
            $table->foreignId('classroom_id')->nullable()->after('room_id')->constrained('classes')->restrictOnDelete();
        });

        // 5. CHECK constraint: exactly-one-of(room_id, classroom_id).
        //    MariaDB 10.4+ menegaskan CHECK — memastikan tidak ada baris
        //    yang keduanya kosong atau keduanya terisi, meski validasi di
        //    aplikasi sudah menangani ini sebagai pertahanan berganda.
        DB::statement('ALTER TABLE exam_schedules ADD CONSTRAINT chk_exam_schedules_placement CHECK ((room_id IS NOT NULL AND classroom_id IS NULL) OR (room_id IS NULL AND classroom_id IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE exam_schedules DROP CONSTRAINT chk_exam_schedules_placement');

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn('classroom_id');

            // Kembalikan room_id ke NOT NULL (data lama semua punya room_id — aman).
            $table->dropForeign(['room_id']);
            $table->foreignId('room_id')->nullable(false)->change();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
        });
    }
};
