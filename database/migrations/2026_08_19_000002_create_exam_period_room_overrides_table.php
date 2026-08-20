<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_period_room_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('supervisor_count');
            $table->timestamps();

            $table->unique(['exam_period_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_period_room_overrides');
    }
};
