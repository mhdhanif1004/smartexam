# AGENTS.md

SmartExam: platform ujian berbasis komputer (CBT) berbasis Laravel 12 dengan peran admin (`admin`), pengawas (`pengawas`), guru mata pelajaran (`guru_mapel`), dan peserta (`peserta`). UI dan komentar kode dalam bahasa Indonesia — pertahankan konvensi itu.

## Stack / entrypoint
- PHP 8.2, Laravel 12 (`app/`, `routes/`), Blade + Alpine.js + Tailwind (token Material Design 3 via CSS vars di `resources/css/app.css`).
- Frontend dibangun dengan Vite (`resources/js/app.js`, `resources/css/app.css`). Deps: Alpine, Chart.js, axios, @hotwired/turbo (opsional), instant.page, pdfjs-dist.
- Import/export Excel (maatwebsite/excel), PDF (barryvdh/laravel-dompdf).
- Skrip `dev` di `composer.json` mengorkestrasi semua layanan dev dalam satu perintah.

## Perintah
- **Dev (semua layanan):** `composer dev` — berjalan paralel: `php artisan serve` (port 8000), `queue:listen --tries=1`, `schedule:work`, `pail --timeout=0`, dan `npm run dev`. **Scheduler harus berjalan** — tanpanya token ujian tidak pernah di-generate dan status jadwal tidak pernah sinkron (lihat `docs/TOKEN_SCHEDULER.md` dan `docs/DEV_WORKFLOW.md`).
- **Frontend:** `npm run dev` (HMR) atau `npm run build` untuk hasil optimasi. Build ulang sebelum commit perubahan frontend / setelah pull.
- **Test PHP:** `php artisan test` (atau `phpunit`). Test JS: `npm run test:js`.
- Gaya: `./vendor/bin/pint` (config default; tidak ada `pint.json`).

## Database
- **Production/dev adalah MySQL** (config `DB_CONNECTION=mysql`, `DB_DATABASE=smartexam`). File `database/database.sqlite` ada tetapi BUKAN yang dipakai aplikasi — jangan berasumsi sqlite.
- **Test PHPUnit memerlukan MySQL yang berjalan dengan database `smartexam_test`** (`phpunit.xml` memaksa `DB_CONNECTION=mysql` / `DB_DATABASE=smartexam_test`). Test fitur memakai `RefreshDatabase` dan akan gagal tanpa DB tersebut dapat dijangkau. Buat dulu sebelum menjalankan suite.
- Timezone adalah `Asia/Jakarta` (diset di `.env`). Hati-hati dengan asumsi timezone dalam logika ujian/token.

## Auth & peran
- `roles`: `admin`, `pengawas`, `guru_mapel`, `peserta` — ditegakkan via alias middleware `role:` dari `EnsureUserHasRole` (abort 403 jika tidak cocok).
- **peserta login dengan `username` (bukan email) + password yang di-generate; pengawas/guru_mapel login dengan email.** Password (`plain_password`) disimpan terenkripsi agar kartu login yang bisa dicetak dapat menampilkannya.
- Seeder membuat demo `admin@smartexam.test` dan mencetak kredensial contoh. `app/Console/Commands/CreateTestStudents.php` (`exam:create-test-students`) membuat akun `peserta` untuk load test (`lt000000001`.. / password `password`).

## Kompleksitas domain yang perlu hati-hati
- **Rotasi token:** `tokens:rotate` (everyMinute) menghasilkan token per-periode. Bergantung pada scheduler. Jangan rusak alur `exam_tokens`.
- **State machine sesi ujian** (`ExamSession`): `not_started` / `in_progress` / `completed` / `timed_out`, plus flag absensi, 3 boolean `violation_flag_*`, dan field kunci admin. Sesi `in_progress` yang macet memblokir early-start untuk seluruh periode (`hasActiveSessionInPeriod()`) — lihat `docs/KNOWN_LIMITATIONS.md`. `sessions:cleanup-stuck` (setiap 5 menit) otomatis meng-time-out sesi menggunakan heartbeat `last_activity_at` (throttle 60 detik).
- **Penskoran** (`app/Services/ExamGradingService.php`): soal essay tidak pernah dinilai otomatis (tetap `null`); hanya tipe objektif yang dihitung ke `total_score`. Rasio lulus adalah 0.7.
- **Targeting soal:** sumber kebenaran tunggal adalah pivot `question_classroom`. Soal tanpa baris pivot kelas tidak akan pernah muncul dalam ujian mana pun — jangan lemahkan `scopeTargetingClassroom`.
- **Pelaporan pelanggaran / anti-cheat** memakai polling klien (`resources/js/violation-polling.js`, Web Worker + suara notifikasi) dan endpoint server. Diuji oleh `tests/js/*` dan `ViolationPollingTest`.

## Konvensi / gotcha
- Copy respons dan seed DB berbahasa Indonesia; pertahankan string UI dan pesan dalam bahasa Indonesia.
- `TURBO_ENABLED` di `.env` mengaktifkan Hotwired Turbo Drive (default false → full reload). Jika mengubah perilaku Turbo, baik gate JS di `app.js` maupun flag layout harus sinkron.
- Session/cache berbasis database; `QUEUE_CONNECTION=database`, jadi worker queue diperlukan untuk job.
- `bootstrap/app.php` menambahkan handler 419 (kedaluwarsa CSRF) dan menambahkan `PreventBackHistoryCache` ke grup web (aplikasi sengaja ketat soal back-cache).
- Server berjalan di port 8000; jika terpakai, mulai `serve` di port lain.

## Dokumen referensi (lokal repo, baca sebelum mengedit subsistem tersebut)
- `docs/DEV_WORKFLOW.md` — layanan mana yang harus berjalan untuk fitur mana.
- `docs/TOKEN_SCHEDULER.md` — setup & troubleshooting scheduler pembuatan token.
- `docs/KNOWN_LIMITATIONS.md` — catatan deadlock sesi macet dan dual-timer.
