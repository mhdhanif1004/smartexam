# Dev Workflow — Services yang Harus Berjalan

SmartExam membutuhkan beberapa service/process yang harus berjalan secara bersamaan selama development. Jika salah satu tidak aktif, fitur tertentu tidak akan berfungsi meski kode sumber sudah benar.

---

## Single Command (Recommended)

Jalankan SATU command untuk start SEMUA service sekaligus:

```bash
composer dev
```

Command ini menjalankan 5 process secara paralel via `concurrently`:

| Process | Perintah | Fungsi |
|---------|----------|--------|
| server | `php artisan serve` | Web server PHP |
| queue | `php artisan queue:listen --tries=1` | Async job queue |
| scheduler | `php artisan schedule:work` | Token rotation & scheduled tasks |
| logs | `php artisan pail --timeout=0` | Real-time log viewer |
| vite | `npm run dev` | Frontend auto-rebuild (HMR) |

**Tidak perlu buka terminal terpisah.** Cukup `composer dev` dan semua berjalan.

> **Catatan Windows:** Process `artisan serve` (server) harus berjalan di port default (8000). Jika port sudah dipakai process lain, process server mungkin gagal start — tapi process lain tetap jalan.

---

## Manual (3+ Terminal)

Jika ingin menjalankan manual (misal karena butuh terminal tertentu saja):

| Terminal | Perintah | Fungsi |
|----------|----------|--------|
| 1 | `php artisan serve` | Web server PHP |
| 2 | `npm run dev` | Frontend auto-rebuild (Vite) |
| 3 | `php artisan schedule:work` | Token rotation & scheduled tasks |
| 4 | `php artisan queue:listen --tries=1` | Async job queue |
| 5 | `php artisan pail --timeout=0` | Real-time log viewer |

Minimal terminal 1, 2, dan 3 harus aktif. Tanpa scheduler (#3), token tidak akan pernah di-generate.

---

## Fitur yang Bergantung pada Setiap Service

### Scheduler (`schedule:work`)
- `tokens:rotate` — generate & rotasi token ujian per 15 menit (auto-generate 5 menit sebelum sesi)
- `exam-schedules:sync-status` — sinkronkan status jadwal berdasarkan waktu

Tanpa scheduler, token tidak akan pernah di-generate. Halaman `/pengawas/tokens` akan menampilkan peringatan amber: "Token belum ter-generate — kemungkinan scheduler belum berjalan".

### Vite (`npm run dev`)
- Build JavaScript (`resources/js/`) dan CSS (`resources/css/`)
- Hot Module Replacement (HMR) — perubahan langsung terlihat di browser
- File build disimpan di `public/build/`

Tanpa Vite, JavaScript/CSS tidak berubah di browser meski source sudah diupdate.

### Queue Worker (`queue:listen`)
- Menjalankan job async seperti email notification
- Tidak kritis untuk core CBT, tapi diperlukan untuk fitur notifikasi

---

## Production / Testing Akhir

```bash
npm run build          # Build frontend optimized
php artisan schedule:work  # Atau cron di production
```

Build sekali untuk menghasilkan file optimized di `public/build/`. Jalankan ini:
- Sebelum commit perubahan frontend
- Saat testing fitur secara final
- Setelah merge atau pull

---

## Troubleshooting Cepat

| Masalah | Solusi |
|---------|--------|
| JS/CSS tidak berubah di browser | Jalankan `npm run dev` atau `npm run build` |
| Token "Menunggu Token" terus | Pastikan `composer dev` sudah jalan (scheduler ada di dalamnya) |
| Token "Belum ter-generate" (amber warning) | Scheduler belum jalan — restart `composer dev` |
| Halaman 500 | Cek `storage/logs/laravel.log` |
| npm install error | Hapus `node_modules/`, jalankan `npm install` ulang |
| Port 8000 sudah dipakai | Stop process lain yang pakai port 8000, atau `php artisan serve --port=8001` |
