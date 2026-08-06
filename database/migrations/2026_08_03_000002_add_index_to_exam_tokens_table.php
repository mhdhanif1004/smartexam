<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_tokens', function (Blueprint $table) {
            $table->index(['exam_schedule_id', 'token_code']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_tokens', function (Blueprint $table) {
            $table->dropIndex(['exam_schedule_id', 'token_code']);
        });
    }
};
