<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan ruangan pengawas per hari dalam satu periode ujian
     * (rotasi pengawas). Satu baris = satu pengawas untuk satu ruangan
     * pada satu tanggal dalam satu periode; satu ruangan boleh punya
     * beberapa baris (sesuai rooms.supervisor_count). Berbeda dari
     * supervisor_attendances yang mencatat kehadiran aktual, tabel ini
     * adalah definisi penugasan (rencana) yang dibuat saat generate.
     */
    public function up(): void
    {
        Schema::create('supervisor_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $table->date('exam_date');
            $table->foreignId('supervisor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rotation_index')->nullable();
            $table->timestamps();

            $table->unique(['exam_period_id', 'exam_date', 'supervisor_id'], 'sra_period_date_supervisor_unique');
            $table->index(['exam_period_id', 'exam_date']);
            $table->index('exam_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_room_assignments');
    }
};
