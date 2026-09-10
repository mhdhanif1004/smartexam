<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->foreignId('exam_type_id')->nullable()->after('name')->constrained('exam_types');
        });

        $uasId = DB::table('exam_types')->where('code', 'uas')->value('id');

        if ($uasId !== null) {
            DB::table('exam_periods')->whereNull('exam_type_id')->update(['exam_type_id' => $uasId]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_periods', function (Blueprint $table) {
            $table->dropForeign(['exam_type_id']);
            $table->dropColumn('exam_type_id');
        });
    }
};
