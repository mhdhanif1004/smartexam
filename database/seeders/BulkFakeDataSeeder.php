<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class BulkFakeDataSeeder extends Seeder
{
    private const USERNAME_CHARS = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Generate banyak data siswa & pengawas acak untuk demo.
     */
    public function run(int $studentCount = 1000, int $supervisorCount = 100): void
    {
        $this->command?->info("Membuat {$studentCount} siswa & {$supervisorCount} pengawas acak...");

        $this->call(ClassroomSeeder::class);

        $classes = Classroom::query()->orderBy('name')->pluck('name')->all();
        if (empty($classes)) {
            $classes = ['X RPL 1', 'X RPL 2', 'XI RPL 1', 'XI RPL 2', 'XII RPL 1', 'XII RPL 2'];
        }

        $faker = fake('id_ID');
        $now = now();
        $sharedHash = Hash::make('password');

        $usedUsernames = User::query()->whereNotNull('username')->pluck('username')->flip()->all();
        $usedEmails = User::query()->whereNotNull('email')->pluck('email')->flip()->all();
        $usedNisn = Student::query()->pluck('nisn')->flip()->all();

        for ($i = 0; $i < $studentCount; $i++) {
            $user = User::create([
                'name' => $faker->name(),
                'email' => null,
                'username' => $this->uniqueUsername($usedUsernames),
                'password' => $sharedHash,
                'plain_password' => 'password',
                'role' => User::ROLE_PESERTA,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Student::create([
                'user_id' => $user->id,
                'nisn' => $this->uniqueNisn($usedNisn, $faker),
                'class_name' => $faker->randomElement($classes),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rooms = $this->ensureRooms();

        for ($i = 0; $i < $supervisorCount; $i++) {
            $user = User::create([
                'name' => $faker->name(),
                'email' => $this->uniqueEmail($usedEmails, $faker),
                'username' => null,
                'password' => $sharedHash,
                'plain_password' => 'password',
                'role' => User::ROLE_PENGAWAS,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Supervisor::create([
                'user_id' => $user->id,
                'room_id' => $rooms->get($i % $rooms->count())->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->placeStudentsIntoRooms($rooms);

        $this->command?->info("Selesai. Semua akun demo berpassword 'password'.");
        $this->command?->info('Contoh akun peserta: username='.User::query()->where('role', User::ROLE_PESERTA)->latest()->value('username').' password=password');
        $this->command?->info('Contoh akun pengawas: email='.User::query()->where('role', User::ROLE_PENGAWAS)->latest()->value('email').' password=password');
    }

    /**
     * Pastikan minimal 20 ruangan tersedia (kapasitas 60) untuk penempatan.
     */
    private function ensureRooms(): Collection
    {
        foreach (range(1, 20) as $i) {
            Room::firstOrCreate(['room_number' => $i], ['capacity' => 60]);
        }

        return Room::query()->orderBy('room_number')->get();
    }

    /**
     * Sebar siswa yang belum punya ruangan ke ruangan secara berurutan,
     * lalu sesuaikan kapasitas ruangan = jumlah siswa yang ditugaskan.
     */
    private function placeStudentsIntoRooms(Collection $rooms): void
    {
        $students = Student::query()->whereNull('room_id')->orderBy('id')->get();

        $students->each(function (Student $student, int $index) use ($rooms) {
            $student->update(['room_id' => $rooms->get($index % $rooms->count())->id]);
        });

        $rooms->each(function (Room $room) {
            $room->update(['capacity' => $room->students()->count()]);
        });
    }

    private function uniqueUsername(array &$used): string
    {
        do {
            $length = random_int(10, 15);
            $candidate = '';
            for ($i = 0; $i < $length; $i++) {
                $candidate .= self::USERNAME_CHARS[random_int(0, strlen(self::USERNAME_CHARS) - 1)];
            }
        } while (isset($used[$candidate]));

        $used[$candidate] = true;

        return $candidate;
    }

    private function uniqueEmail(array &$used, Generator $faker): string
    {
        do {
            $candidate = $faker->unique()->safeEmail();
        } while (isset($used[$candidate]));

        $used[$candidate] = true;

        return $candidate;
    }

    private function uniqueNisn(array &$used, Generator $faker): string
    {
        do {
            $candidate = sprintf('%010d', $faker->unique()->numberBetween(0, 9999999999));
        } while (isset($used[$candidate]));

        $used[$candidate] = true;

        return $candidate;
    }
}
