<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shift ujian untuk siswa (1 shift tetap untuk seluruh masa ujian, sejajar
     * dengan room_id). Nilai null selama shift siswa belum ditentukan.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('shift')->nullable()->after('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
