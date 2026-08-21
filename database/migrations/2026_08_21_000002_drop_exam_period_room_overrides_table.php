<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_period_room_overrides');
    }

    public function down(): void
    {
        Schema::create('exam_period_room_overrides', function ($table) {
            $table->id();
            $table->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('supervisor_count')->default(1);
            $table->timestamps();
            $table->unique(['exam_period_id', 'room_id']);
        });
    }
};
