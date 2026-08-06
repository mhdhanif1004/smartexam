<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat master data kelas default untuk SMK, lalu otomatis menambahkan
     * nilai class_name yang masih dipakai siswa yang sudah ada tapi belum
     * terdaftar di tabel classes.
     */
    public function run(): void
    {
        $grades = ['X', 'XI', 'XII'];
        $programs = ['RPL', 'TKJ', 'MM'];

        foreach ($grades as $grade) {
            foreach ($programs as $program) {
                foreach ([1, 2] as $number) {
                    Classroom::firstOrCreate(['name' => "{$grade} {$program} {$number}"]);
                }
            }
        }

        // Jangan sampai ada kelas legacy (hasil ketik manual) yang tidak
        // terwakili di master data, supaya siswa lama tidak menjadi invalid.
        Student::query()
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->filter()
            ->each(fn (string $name) => Classroom::firstOrCreate(['name' => $name]));
    }
}
