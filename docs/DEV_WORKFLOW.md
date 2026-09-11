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
| **CSS/JS hilang total** (SVG ikon raksasa, tanpa styling) | Lihat section **public/hot basi** di bawah |

---

## `public/hot` Basi (CSS/JS Hilang Total)

### Apa itu `public/hot`

File `public/hot` berisi URL Vite dev server (mis. `http://[::1]:5173`). Selama
`npm run dev` berjalan, Laravel membaca file ini dan memuat asset langsung dari
dev server. File ini dibuat otomatis saat Vite start dan **dihapus otomatis saat
Vite exit graceful** (Ctrl+C).

### Kenapa bisa basi

Saat Vite **di-kill paksa** (tutup window terminal/VS Code, komputer sleep,
`composer dev` dimatikan mendadak — bukan Ctrl+C), Vite **tidak sempat menghapus**
`public/hot`. File orphan tetap ada dan menunjuk port lama yang sudah mati →
Laravel tetap coba load asset dari situ → **seluruh halaman kehilangan styling**
(SVG ikon jadi raksasa tanpa constraint ukuran). Ini keterbatasan Windows:
kill paksa tidak mengirim signal graceful shutdown sama sekali, jadi tidak ada
cara murni-kode mencegahnya — yang ada hanya deteksi dini + recovery cepat.

### Cara ketahui

- Semua halaman tanpa styling (CSS hilang, ikon raksasa)
- **Banner merah di pojok atas** setiap halaman (local env saja): *"File Vite dev
  server (`public/hot`) terdeteksi basi..."* — muncul dalam ≤30 detik setelah
  server mati
- `storage/logs/laravel.log` berisi warning `public/hot terdeteksi basi`

### Fix cepat (1 perintah)

```bash
npm run build
```

`npm run build` otomatis menghapus `public/hot` basi sebelum build (lihat
`scripts/remove-stale-hot.mjs`). Alternatif manual:

```bash
del public\hot         # Windows
rm public/hot          # Linux/macOS
npm run build
```

### Pencegahan otomatis

- `npm run dev` → `predev` otomatis menghapus `public/hot` lama sebelum Vite
  menulis yang baru (jadi restart dev selalu bersih, suka-suka port-nya beda
  atau sama)
- Banner merah + log warning saat `php artisan serve` mendeteksi hot basi —
  developer langsung sadar tanpa debug CSS hilang dari nol
