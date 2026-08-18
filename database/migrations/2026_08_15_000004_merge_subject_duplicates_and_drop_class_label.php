<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subject berubah menjadi 1 record murni per mata pelajaran. Semua record
     * yang nama sama (sebelumnya dipisah oleh class_label per tingkat) digabung
     * ke record dengan id terkecil, FK yang menunjuk ke record lain diarahkan
     * ulang, lalu class_label dihapus dan unique code dikembalikan.
     *
     * Dicari berdasarkan nama (bukan id hardcode) agar aman di DB manapun.
     */
    public function up(): void
    {
        $groups = DB::table('subjects')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('subjects')
                ->where('name', $group->name)
                ->orderBy('id')
                ->pluck('id')
                ->values();

            $keep = $ids->shift();
            $removed = $ids->all();

            if ($removed === []) {
                continue;
            }

            DB::table('questions')->whereIn('subject_id', $removed)->update(['subject_id' => $keep]);
            DB::table('exam_schedules')->whereIn('subject_id', $removed)->update(['subject_id' => $keep]);
            DB::table('subjects')->whereIn('id', $removed)->delete();
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_code_class_label_unique');
            $table->dropColumn('class_label');
            $table->unique('code', 'subjects_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_code_unique');
            $table->string('class_label')->nullable()->after('name');
            $table->unique(['code', 'class_label'], 'subjects_code_class_label_unique');
        });
    }
};
