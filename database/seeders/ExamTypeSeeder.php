<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

class ExamTypeSeeder extends Seeder
{
    /**
     * Jenis ujian default. Idempotent via firstOrCreate supaya aman dijalankan
     * ulang (migrasi create_exam_types sudah mengisi data dasar yang sama).
     */
    public function run(): void
    {
        $types = [
            ['code' => 'harian',    'name' => 'Harian',    'sort_order' => 1],
            ['code' => 'uts',       'name' => 'UTS',       'sort_order' => 2],
            ['code' => 'uas',       'name' => 'UAS',       'sort_order' => 3],
            ['code' => 'kehadiran', 'name' => 'Kehadiran', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            ExamType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
