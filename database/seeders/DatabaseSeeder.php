<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command?->info('Seeding data awal SmartExam...');

        // Master data kelas dipakai dropdown form siswa & validasi import.
        $this->call(ClassroomSeeder::class);

        // 1 akun administrator.
        User::factory()->admin()->create([
            'name' => 'Administrator',
            'email' => 'admin@smartexam.test',
        ]);

        // 5 ruangan ujian.
        $rooms = Room::factory()->count(5)->create();

        // 5 pengawas, masing-masing ditugaskan ke 1 ruangan.
        User::factory()->pengawas()->count(5)->create()->each(function (User $user, int $index) use ($rooms) {
            Supervisor::create([
                'user_id' => $user->id,
                'room_id' => $rooms->get($index % $rooms->count())->id,
            ]);
        });

        // 30 siswa (user ber-role peserta otomatis dibuat oleh factory), semua
        // pada satu kelas agar mudah didemonstrasikan "1 kelas dipecah ke
        // beberapa ruangan".
        Student::factory()->count(30)->create(['class_name' => 'XI RPL 1']);

        // Peserta login memakai username acak (bukan email) + password acak.
        // Password plaintext disimpan terenkripsi agar kartu login bisa dicetak.
        $generator = app(CredentialGenerator::class);

        Student::query()->with('user')->get()->each(function (Student $student) use ($generator) {
            $password = $generator->password();
            $student->user->update([
                'username' => $generator->username(),
                'password' => $password,
                'plain_password' => $password,
            ]);
        });

        Student::query()->with('user')->orderBy('nisn')->limit(3)->get()->each(function (Student $student) {
            $this->command?->info('  Contoh akun peserta: username='.$student->user->username.' password='.$student->user->plain_password.' (NISN '.$student->nisn.')');
        });

        // Pengawas login memakai email (bukan username) + password acak.
        Supervisor::query()->with('user')->get()->each(function (Supervisor $supervisor) use ($generator) {
            $password = $generator->password();
            $supervisor->user->update([
                'password' => $password,
                'plain_password' => $password,
            ]);
        });

        Supervisor::query()->with('user')->orderBy('user_id')->limit(2)->get()->each(function (Supervisor $supervisor) {
            $this->command?->info('  Contoh akun pengawas: email='.$supervisor->user->email.' password='.$supervisor->user->plain_password.' ('.$supervisor->room?->name.')');
        });

        // Penempatan tetap: semua siswa dibagi rata ke ruangan-ruangan yang
        // ada (students.room_id). Kapasitas ruangan diisi jumlah siswa yang
        // ditugaskan. 1 siswa = 1 ruangan untuk seluruh masa ujian.
        $students = Student::query()->orderBy('nisn')->get();

        $students->each(function (Student $student, int $index) use ($rooms) {
            $student->update(['room_id' => $rooms->get($index % $rooms->count())->id]);
        });

        $rooms->each(function (Room $room) {
            $room->update(['capacity' => $room->students()->count()]);
        });

        // 5 mata pelajaran. Satu baris = 1 mapel murni (kelas target diatur
        // per soal lewat pivot question_classroom).
        $subjects = collect([
            ['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90],
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90],
            ['code' => 'BIG', 'name' => 'Bahasa Inggris', 'default_duration_minutes' => 60],
            ['code' => 'PW', 'name' => 'Pemrograman Web', 'default_duration_minutes' => 120],
            ['code' => 'BD', 'name' => 'Basis Data', 'default_duration_minutes' => 60],
        ])->map(fn (array $data) => Subject::create($data));

        // Minimal 10 soal per mata pelajaran dengan variasi jenis soal.
        // Semua soal ditargetkan ke kelas demo "XI RPL 1" agar langsung
        // berfungsi saat ujian dijalankan.
        $subjects->each(function (Subject $subject) {
            Question::factory()->count(10)->create([
                'subject_id' => $subject->id,
            ])->each(function (Question $question) {
                $question->classrooms()->sync(Classroom::where('name', 'XI RPL 1')->value('id'));
            });
        });

        // Jadwal ujian hari ini: 1 mata pelajaran per ruangan, kelas yang sama.
        // Peserta tiap jadwal otomatis = siswa yang ditempatkan di ruangan itu
        // (students.room_id), bukan lagi tabel exam_participants.
        $startTimes = ['08:00', '10:00', '13:00', '15:00', '17:00'];

        $subjects->values()->each(function (Subject $subject, int $index) use ($rooms, $startTimes) {
            $room = $rooms->get($index % $rooms->count());
            $start = Carbon::today()->setTimeFromTimeString($startTimes[$index % count($startTimes)]);
            $duration = (int) ($subject->default_duration_minutes ?? 90);

            ExamSchedule::create([
                'subject_id' => $subject->id,
                'room_id' => $room->id,
                'class_name' => 'XI RPL 1',
                'exam_date' => Carbon::today()->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $start->copy()->addMinutes($duration)->format('H:i:s'),
                'duration_minutes' => $duration,
                'status' => now()->lt($start) ? ExamSchedule::STATUS_SCHEDULED
                    : (now()->lte($start->copy()->addMinutes($duration)) ? ExamSchedule::STATUS_ONGOING : ExamSchedule::STATUS_FINISHED),
            ]);
        });
        $this->command?->info('Seeding selesai. Admin: admin@smartexam.test / password.');
    }
}
