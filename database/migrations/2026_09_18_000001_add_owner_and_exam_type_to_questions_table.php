<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_guru_mapel_id')->nullable()->after('subject_id');
            $table->unsignedBigInteger('exam_type_id')->nullable()->after('teacher_guru_mapel_id');

            $table->foreign('teacher_guru_mapel_id')
                ->references('id')->on('guru_mapels')
                ->nullOnDelete();
            $table->foreign('exam_type_id')
                ->references('id')->on('exam_types')
                ->nullOnDelete();

            $table->index('teacher_guru_mapel_id', 'questions_teacher_guru_mapel_id_index');
            $table->index('exam_type_id', 'questions_exam_type_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_teacher_guru_mapel_id_index');
            $table->dropIndex('questions_exam_type_id_index');
            $table->dropForeign(['teacher_guru_mapel_id']);
            $table->dropForeign(['exam_type_id']);
            $table->dropColumn(['teacher_guru_mapel_id', 'exam_type_id']);
        });
    }
};
