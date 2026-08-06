<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->boolean('handled_by_supervisor')->default(false)->after('reported_by');
            $table->timestamp('handled_at')->nullable()->after('handled_by_supervisor');
            $table->foreignId('handled_by')->nullable()->after('handled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['handled_by_supervisor', 'handled_at']);
        });
    }
};
