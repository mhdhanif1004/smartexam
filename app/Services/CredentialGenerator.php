<?php

namespace App\Services;

use App\Models\User;

class CredentialGenerator
{
    /**
     * Karakter aman yang tidak membingungkan saat dibaca/diketik:
     * tanpa 0/O, 1/l, dan I (tampil mirip).
     */
    private const USERNAME_CHARS = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Karakter untuk password: huruf besar/kecil dan angka.
     */
    private const PASSWORD_CHARS = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Buat username acak unik (10-15 karakter alfanumerik) untuk peserta.
     *
     * Username dicek ke tabel users agar tidak bentrok dengan akun lain.
     */
    public function username(int $minLength = 10, int $maxLength = 15): string
    {
        do {
            $length = random_int($minLength, $maxLength);
            $candidate = $this->random(self::USERNAME_CHARS, $length);
        } while (User::query()->where('username', $candidate)->exists());

        return $candidate;
    }

    /**
     * Buat password acak (default 10 karakter) yang memenuhi aturan min:8.
     */
    public function password(int $length = 10): string
    {
        return $this->random(self::PASSWORD_CHARS, max(8, $length));
    }

    private function random(string $charset, int $length): string
    {
        $max = strlen($charset) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }

        return $result;
    }
}
