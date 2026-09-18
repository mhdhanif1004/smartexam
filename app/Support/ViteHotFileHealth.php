<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Deteksi dini file `public/hot` yang basi (menunjuk port Vite dev server
 * yang sudah mati — umum terjadi di Windows saat `composer dev` di-kill
 * paksa: window terminal ditutup, Vite tidak sempat cleanup `public/hot`).
 *
 * Hasil check di-cache 30 detik (bukan dijalankan per-request), jadi tidak
 * membebani halaman biasa.
 */
class ViteHotFileHealth
{
    private const CACHE_KEY = 'smartexam.vite_hot_stale';

    private const CACHE_TTL_SECONDS = 30;

    /**
     * Jendela "masih starting" (detik).
     *
     * Saat `composer dev` dijalankan, Vite langsung menulis `public/hot`
     * tetapi butuh ~1-2 detik untuk benar-benar mendengarkan port-nya
     * (log: "VITE ready in 1470ms"). Kalau halaman dibuka dalam jendela ini,
     * tes koneksi port akan gagal dan salah menyimpulkan "basi". File yang
     * masih sangat baru dianggap sedang starting, bukan basi.
     */
    private const STARTUP_GRACE_SECONDS = 10;

    /**
     * True kalau `public/hot` ada tapi port Vite-nya sudah tidak listen.
     */
    public function isStale(): bool
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return false;
        }

        // File yang baru ditulis (Vite baru saja start) masih dalam grace
        // period: anggap "sedang starting", bukan basi. Sengaja TIDAK
        // di-cache supaya begitu grace period lewat, pengecekan port yang
        // sebenarnya tetap berjalan (hasil cache lama tidak memblokir).
        if ($this->isFresh($hotFile)) {
            return false;
        }

        return (bool) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($hotFile) {
            return $this->check($hotFile);
        });
    }

    /**
     * True kalau `public/hot` ditulis kurang dari STARTUP_GRACE_SECONDS yang
     * lalu — indikasi Vite dev server baru saja (atau sedang) start.
     */
    private function isFresh(string $hotFile): bool
    {
        // PHP menyimpan stat file per proses; bila file ditulis ulang dalam
        // proses yang sama (mis. Vite restart via composer dev baru), mtime
        // bisa basi — paksa baca segar dulu.
        clearstatcache(true, $hotFile);

        $mtime = @filemtime($hotFile);

        return $mtime !== false && (time() - $mtime) < self::STARTUP_GRACE_SECONDS;
    }

    private function check(string $hotFile): bool
    {
        $url = trim((string) file_get_contents($hotFile));

        if ($url === '') {
            return true;
        }

        $parsed = parse_url($url);
        $port = $parsed['port'] ?? null;

        if ($port === null) {
            return true;
        }

        // parse_url('http://[::1]:5173') memberi host '[::1]' (ikut bracket
        // IPv6). fsockopen tidak suka bracket — strip dulu.
        $host = trim((string) ($parsed['host'] ?? ''), '[]');

        if ($host === '') {
            return true;
        }

        // Coba host persis dari file; kalau gagal, coba localhost & 127.0.0.1.
        // Vite di Windows sering bind hanya ke [::1] (IPv6) — bukan 127.0.0.1 —
        // jadi 127.0.0.1 saja tidak cukup. Urutan: host stripped, rawHost
        // (ikut bracket), localhost (resolve ke ::1/127.0.0.1), 127.0.0.1.
        $rawHost = (string) ($parsed['host'] ?? '');
        $candidates = array_unique([$host, $rawHost, 'localhost', '127.0.0.1']);
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($this->isPortOpen($candidate, (int) $port)) {
                return false; // port listen -> tidak basi
            }
        }

        return true;
    }

    private function isPortOpen(string $host, int $port): bool
    {
        // Timeout 0.2s cukup untuk localhost (konek <5ms bila hidup) tapi
        // tidak blokir request 2 detik bila Vite mati (4 kandidat × 0.5s).
        $fp = @fsockopen($host, $port, $errno, $errstr, 0.2);

        if ($fp !== false) {
            fclose($fp);

            return true;
        }

        // fsockopen di Windows butuh bracket untuk IPv6: '::1' gagal
        // (10049), '[::1]' berhasil. Coba pasangan bracket bila host
        // terlihat seperti IPv6 tanpa bracket.
        if (str_contains($host, ':') && $host[0] !== '[') {
            $fp2 = @fsockopen('['.$host.']', $port, $errno, $errstr, 0.2);
            if ($fp2 !== false) {
                fclose($fp2);

                return true;
            }
        }

        return false;
    }
}
