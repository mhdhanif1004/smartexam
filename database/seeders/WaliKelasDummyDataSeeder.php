<?php

namespace Database\Seeders;

use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Database\Seeder;

class WaliKelasDummyDataSeeder extends Seeder
{
    /**
     * Seeder data dummy untuk modul Wali Kelas — nilai sikap.
     *
     * Membuat:
     *  1 kelas "X TKR 1"
     *  1 akun wali kelas (email: wali.tkr1@smartexam.test, password: password)
     *  6 siswa di kelas itu
     *  8 contoh nilai sikap (3 siswa × 2 aspek, semester 2 aktif)
     */
    public function run(): void
    {
        // ── 1. Kelas ──────────────────────────────────────────────
        $classroom = Classroom::firstOrCreate(['name' => 'X TKR 1']);

        // ── 2. Wali Kelas ─────────────────────────────────────────
        $userWali = User::firstOrCreate(
            ['email' => 'wali.tkr1@smartexam.test'],
            [
                'name' => 'Pak Wali — TKR 1',
                'password' => bcrypt('password'),
                'plain_password' => 'password',
                'role' => 'wali_kelas',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $waliKelas = WaliKelas::firstOrCreate(
            ['user_id' => $userWali->id],
            ['classroom_id' => $classroom->id],
        );

        // ── 3. Siswa di kelas ────────────────────────────────────
        $students = collect([
            ['name' => 'Andi Saputra',      'nisn' => '9001000001'],
            ['name' => 'Budi Santoso',       'nisn' => '9001000002'],
            ['name' => 'Citra Dewi',         'nisn' => '9001000003'],
            ['name' => 'Dian Permata',       'nisn' => '9001000004'],
            ['name' => 'Eka Rahmawati',      'nisn' => '9001000005'],
            ['name' => 'Fajar Nugroho',      'nisn' => '9001000006'],
        ])->map(fn (array $s) => Student::firstOrCreate(
            ['nisn' => $s['nisn']],
            [
                'user_id' => User::firstOrCreate(
                    ['email' => strtolower(str_replace(' ', '.', $s['name'])).'@smartexam.test'],
                    [
                        'name' => $s['name'],
                        'role' => 'peserta',
                        'password' => bcrypt('password'),
                        'plain_password' => 'password',
                        'is_active' => true,
                    ],
                )->id,
                'classroom_id' => $classroom->id,
                'class_name' => 'X TKR 1',
            ],
        ));

        // ── 4. Semester (pastikan ada yang aktif) ─────────────────
        Semester::firstOrCreate(
            ['year' => '2024/2025', 'semester' => 1],
            ['is_active' => false],
        );

        $semesterAktif = Semester::firstOrCreate(
            ['year' => '2024/2025', 'semester' => 2],
            ['is_active' => true],
        );

        // ── 5. Aspek Sikap ───────────────────────────────────────
        $aspects = collect([
            'Kedisiplinan',
            'Kerapian',
            'Sopan Santun',
            'Kerja Sama',
            'Tanggung Jawab',
        ])->map(fn (string $name) => AttitudeAspect::firstOrCreate(['name' => $name]));

        // ── 6. Contoh Nilai Sikap ────────────────────────────────
        //    3 siswa pertama × 2 aspek (Kedisiplinan, Kerja Sama) → 6 nilai
        //    2 siswa pertama × 1 aspek tambahan (Tanggung Jawab)   → 2 nilai
        $gradeData = [
            // [index siswa, nama aspek, nilai, catatan]
            [0, 'Kedisiplinan',   88, 'Selalu tepat waktu masuk kelas'],
            [0, 'Kerja Sama',     85, 'Aktif dalam kelompok'],
            [1, 'Kedisiplinan',   75, 'Kadang terlambat'],
            [1, 'Kerja Sama',     80, 'Kooperatif dengan teman'],
            [2, 'Kedisiplinan',   92, 'Sangat disiplin'],
            [2, 'Kerja Sama',     90, 'Sering membantu teman'],
            [0, 'Tanggung Jawab', 82, 'Tugas selalu selesai'],
            [1, 'Tanggung Jawab', 78, 'Tugas biasa selesai tepat waktu'],
        ];

        $waliKelas->load('user');

        foreach ($gradeData as [$studentIdx, $aspectName, $score, $note]) {
            $student = $students[$studentIdx];
            $aspect = $aspects->firstWhere('name', $aspectName);

            AttitudeGrade::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'attitude_aspect_id' => $aspect->id,
                    'semester_id' => $semesterAktif->id,
                ],
                [
                    'wali_kelas_id' => $waliKelas->id,
                    'score' => $score,
                    'note' => $note,
                ],
            );
        }

        $this->command?->info('Wali Kelas dummy — kredensial login:');
        $this->command?->info('  Email    : wali.tkr1@smartexam.test');
        $this->command?->info('  Password : password');
        $this->command?->info('  Kelas    : X TKR 1 (6 siswa, 8 nilai sikap)');
    }
}
