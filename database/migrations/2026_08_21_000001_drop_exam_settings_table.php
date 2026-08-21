<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_settings');
    }

    public function down(): void
    {
        Schema::create('exam_settings', function ($table) {
            $table->id();
            $table->string('nama_sekolah')->default('');
            $table->string('tempat')->default('');
            $table->string('nama_kepala_sekolah')->default('');
            $table->string('jabatan_kepala_sekolah')->default('');
            $table->tinyInteger('max_supervisors_per_room')->unsigned()->default(3);
            $table->timestamps();
        });
    }
};
