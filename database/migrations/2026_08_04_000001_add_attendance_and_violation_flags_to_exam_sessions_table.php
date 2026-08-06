<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->boolean('attendance_confirmed')->default(false)->after('attendance_status');
            $table->timestamp('attendance_confirmed_at')->nullable()->after('attendance_confirmed');
            $table->foreignId('attendance_confirmed_by')->nullable()->after('attendance_confirmed_at')
                ->constrained('users')->nullOnDelete();

            $table->boolean('violation_flag_1')->default(false)->after('attendance_confirmed_by');
            $table->boolean('violation_flag_2')->default(false)->after('violation_flag_1');
            $table->boolean('violation_flag_3')->default(false)->after('violation_flag_2');

            $table->boolean('locked_by_admin')->default(false)->after('violation_flag_3');
            $table->timestamp('locked_by_admin_at')->nullable()->after('locked_by_admin');
            $table->foreignId('locked_by_admin_by')->nullable()->after('locked_by_admin_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_confirmed_by');
            $table->dropConstrainedForeignId('locked_by_admin_by');
            $table->dropColumn([
                'attendance_confirmed',
                'attendance_confirmed_at',
                'violation_flag_1',
                'violation_flag_2',
                'violation_flag_3',
                'locked_by_admin',
                'locked_by_admin_at',
            ]);
        });
    }
};
