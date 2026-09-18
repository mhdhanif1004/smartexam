<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tegakkan classroom_id NOT NULL pada teacher_subject_class_assignments.
     *
     * Setiap penugasan guru×mapel wajib eksplisit ke satu kelas; baris
     * wildcard (classroom_id null) tidak lagi diperbolehkan. Prasyarat:
     * migration cleanup_null_classroom_assignments harus sudah dijalankan
     * agar tidak ada baris NULL tersisa saat constraint diterapkan.
     */
    public function up(): void
    {
        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('classroom_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse: kembalikan classroom_id menjadi nullable.
     */
    public function down(): void
    {
        Schema::table('teacher_subject_class_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('classroom_id')->nullable()->change();
        });
    }
};