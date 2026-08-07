<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supervisor_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['hadir', 'tidak_hadir'])->default('tidak_hadir');
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['supervisor_id', 'exam_schedule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_attendances');
    }
};
