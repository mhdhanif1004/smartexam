<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_tokens');

        Schema::create('exam_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $table->string('token_code', 8);
            $table->unsignedInteger('rotation_index');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->timestamps();

            $table->unique(['exam_period_id', 'token_code']);
            $table->index(['exam_period_id', 'rotation_index']);
            $table->index(['exam_period_id', 'valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_tokens');

        Schema::create('exam_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('token_code')->unique();
            $table->dateTime('valid_until');
            $table->timestamps();
        });
    }
};
