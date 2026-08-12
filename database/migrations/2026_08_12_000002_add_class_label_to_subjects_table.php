<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom class_label (kelas/tingkat) pada mata pelajaran. Kolom
     * nullable di level database karena ada baris lama; validasi aplikasi
     * (Store/UpdateSubjectRequest) mewajibkan isian untuk data baru.
     *
     * Constraint unique code lama dilonggarkan menjadi kombinasi
     * (code, class_label) agar kode yang sama boleh dipakai untuk beberapa
     * kelas/tingkat (misal MTK untuk X, XI, dan XII).
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('class_label')->nullable()->after('name');
            $table->dropUnique('subjects_code_unique');
            $table->unique(['code', 'class_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_code_class_label_unique');
            $table->unique('code');
            $table->dropColumn('class_label');
        });
    }
};
