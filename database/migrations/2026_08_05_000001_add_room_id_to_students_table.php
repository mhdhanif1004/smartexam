<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Satu siswa hanya terhubung ke satu ruangan untuk seluruh masa ujian
     * (roster tetap). room_id bernilai null selama siswa belum ditempatkan.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('room_id')
                ->nullable()
                ->after('class_name')
                ->constrained()
                ->nullOnDelete();

            $table->index('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
