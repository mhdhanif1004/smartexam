<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTestStudents extends Command
{
    protected $signature = 'exam:create-test-students {count=300 : jumlah akun peserta uji yang diinginkan}';

    protected $description = 'Buat akun peserta untuk load testing login (username lt000000001..lt, password: password).';

    public function handle(): int
    {
        $target = max(0, (int) $this->argument('count'));

        $existing = DB::table('users')
            ->where('username', 'like', 'lt%')
            ->count();

        $toCreate = $target - $existing;

        if ($toCreate <= 0) {
            $this->info("Sudah ada {$existing} akun uji (target {$target}). Tidak ada yang dibuat.");

            return self::SUCCESS;
        }

        $hash = Hash::make('password');
        $now = now()->toDateTimeString();

        $users = [];
        for ($n = $existing + 1; $n <= $target; $n++) {
            $users[] = [
                'name' => 'Peserta Uji '.$n,
                'username' => 'lt'.str_pad((string) $n, 9, '0', STR_PAD_LEFT),
                'email' => null,
                'role' => 'peserta',
                'is_active' => true,
                'email_verified_at' => $now,
                'password' => $hash,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($users, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $newUsernames = array_column($users, 'username');
        $loadTestClassId = Classroom::idForName('X LT 1');
        $students = DB::table('users')
            ->whereIn('username', $newUsernames)
            ->get(['id', 'username'])
            ->map(fn (object $user) => [
                'user_id' => $user->id,
                'nisn' => sprintf('%010d', 2000000000 + $user->id),
                'class_name' => 'X LT 1',
                'classroom_id' => $loadTestClassId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        foreach (array_chunk($students, 500) as $chunk) {
            DB::table('students')->insert($chunk);
        }

        $first = 'lt'.str_pad((string) ($existing + 1), 9, '0', STR_PAD_LEFT);
        $last = 'lt'.str_pad((string) $target, 9, '0', STR_PAD_LEFT);

        $this->info("Dibuat {$toCreate} akun peserta uji (total {$target}).");
        $this->info("Username: {$first} s.d. {$last} / password");

        return self::SUCCESS;
    }
}
