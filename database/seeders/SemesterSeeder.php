<?php

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Semester::firstOrCreate(
            ['year' => '2024/2025', 'semester' => 1],
            ['is_active' => false]
        );

        Semester::firstOrCreate(
            ['year' => '2024/2025', 'semester' => 2],
            ['is_active' => true]
        );
    }
}
