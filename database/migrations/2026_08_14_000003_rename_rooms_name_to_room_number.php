<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti kolom rooms.name (teks, mis. "Ruang 80") menjadi rooms.room_number
     * (integer, unique). Data lama diekstrak angkanya: "Ruang 80" -> 80.
     * Baris tanpa angka atau angka yang bertabrakan diberi nomor cadangan
     * berikutnya agar selalu unik.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('room_number')->nullable()->after('name');
        });

        $used = [];
        $fallback = 0;

        DB::table('rooms')->orderBy('id')->get(['id', 'name'])->each(function ($room) use (&$used, &$fallback) {
            $number = null;

            if (preg_match('/\d+/', (string) $room->name, $matches)) {
                $number = (int) $matches[0];
            }

            if ($number !== null && array_key_exists($number, $used)) {
                $number = null;
            }

            if ($number === null) {
                do {
                    $fallback++;
                } while (array_key_exists($fallback, $used));

                $number = $fallback;
            }

            $used[$number] = true;

            DB::table('rooms')->where('id', $room->id)->update(['room_number' => $number]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('room_number')->nullable(false)->change();
            $table->dropColumn('name');
            $table->unique('room_number', 'rooms_room_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique('rooms_room_number_unique');
            $table->string('name')->nullable()->after('room_number');
        });

        DB::table('rooms')->get(['id', 'room_number'])->each(function ($room) {
            DB::table('rooms')->where('id', $room->id)->update(['name' => 'Ruang '.$room->room_number]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn('room_number');
        });
    }
};
