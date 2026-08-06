<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pengawas boleh "dilepas" dari sebuah ruangan (room_id = null) sehingga
     * bisa ditugaskan ulang. Saat ruangan dihapus, pengawas tidak ikut
     * terhapus; room_id-nya cukup menjadi null.
     */
    public function up(): void
    {
        Schema::table('supervisors', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supervisors', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
        });
    }
};
