<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->string('grade_level', 5)->nullable()->after('name');
            $table->unsignedSmallInteger('session_number')->nullable()->after('grade_level');
        });
    }

    public function down(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->dropColumn(['grade_level', 'session_number']);
        });
    }
};
