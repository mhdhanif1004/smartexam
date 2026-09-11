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
     * True kalau `public/hot` ada tapi port Vite-nya sudah tidak listen.
     */
    public function isStale(): bool
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return false;
        }

        return (bool) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($hotFile) {
            return $this->check($hotFile);
        });
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

        // Coba host persis dari file; kalau gagal, coba 127.0.0.1 (Vite di
        // Windows sering bind ke localhost/IPv4 di samping IPv6).
        return ! $this->isPortOpen($host, (int) $port)
            && ! $this->isPortOpen('127.0.0.1', (int) $port);
    }

    private function isPortOpen(string $host, int $port): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, 0.5);

        if ($fp !== false) {
            fclose($fp);

            return true;
        }

        return false;
    }
}
