<?php

namespace Database\Seeders;

use App\Models\AttitudeAspect;
use Illuminate\Database\Seeder;

class AttitudeAspectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aspects = [
            [
                'name' => 'Kedisiplinan',
                'description' => 'Kepatuhan terhadap tata tertib, ketepatan waktu, dan kehadiran.',
            ],
            [
                'name' => 'Kerapian',
                'description' => 'Kerapian berpakaian, kebersihan diri, dan kelengkapan atribut.',
            ],
            [
                'name' => 'Sopan Santun',
                'description' => 'Perilaku hormat kepada guru, staf, dan sesama teman.',
            ],
            [
                'name' => 'Kerja Sama',
                'description' => 'Kemampuan bekerja dalam kelompok dan tolong menolong.',
            ],
            [
                'name' => 'Tanggung Jawab',
                'description' => 'Menyelesaikan tugas dan kewajiban dengan baik dan tepat waktu.',
            ],
        ];

        foreach ($aspects as $aspect) {
            AttitudeAspect::firstOrCreate(
                ['name' => $aspect['name']],
                ['description' => $aspect['description']]
            );
        }
    }
}
