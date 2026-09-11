<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur Guru Mapel untuk membuat jadwal ujian sendiri.
     * Hanya exam_types dengan `boleh_dijadwalkan_guru = true` yang boleh
     * dipilih guru saat membuat jadwal (classroom-based, tanpa ruang fisik).
     *
     * Default false — Admin harus mengecek secara eksplisit jenis ujian
     * mana yang boleh dijadwalkan guru.
     */
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->boolean('boleh_dijadwalkan_guru')->default(false)->after('is_active');
        });

        // Set Harian & UTS sebagai jenis yang boleh dijadwalkan guru.
        DB::table('exam_types')
            ->whereIn('code', ['harian', 'uts'])
            ->update(['boleh_dijadwalkan_guru' => true]);
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn('boleh_dijadwalkan_guru');
        });
    }
};
