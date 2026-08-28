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

    /**
     * Bagian lokal email dari sebuah nama: lowercase semua, buang spasi dan
     * karakter selain huruf/angka. Contoh: "Yanto Sudirman" => "yantosudirman".
     */
    public function emailLocalPart(string $name): string
    {
        $normalized = strtolower(trim($name));

        return preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';
    }

    /**
     * Buat email unik berbasis @gmail.com dari nama. Bila bagian lokal sudah
     * dipakai user lain, tambahkan angka sebelum "@gmail.com" (2, 3, dst)
     * sampai ditemukan kombinasi yang belum terpakai, sehingga guru tetap bisa
     * dibuat meski nama mengarah ke email yang bentrok.
     */
    public function uniqueEmail(string $name): string
    {
        $localPart = $this->emailLocalPart($name) ?: 'guru';
        $candidate = $localPart.'@gmail.com';

        $taken = fn (string $email): bool => User::query()->where('email', $email)->exists();

        if (! $taken($candidate)) {
            return $candidate;
        }

        for ($i = 2; ; $i++) {
            $candidate = $localPart.$i.'@gmail.com';

            if (! $taken($candidate)) {
                return $candidate;
            }
        }
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
