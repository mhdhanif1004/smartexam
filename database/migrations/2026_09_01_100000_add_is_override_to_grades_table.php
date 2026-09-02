<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda bahwa nilai grades ini merupakan hasil override/koreksi manual
     * guru (bukan sinkron otomatis dari ExamResult). Nilai yang tampil di
     * halaman Nilai guru = skor ExamResult otomatis, KECUALI baris grades
     * ber-is_override, maka skor grades itulah yang final.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};
