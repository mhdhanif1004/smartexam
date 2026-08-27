# Token Scheduler — Setup & Deployment

## Masalah

Halaman `/pengawas/tokens` menampilkan "Menunggu Token" terus-menerus meskipun sesi ujian sudah berjalan.

**Penyebab**: Tabel `exam_tokens` kosong karena Laravel Scheduler belum pernah dieksekusi.

`routes/console.php` mendaftarkan jadwal:
```php
Schedule::command('tokens:rotate')->everyMinute();
```

Tetapi scheduler butuh proses eksternal yang memanggil `php artisan schedule:rotate` secara berkala. Di local dev (`php artisan serve`), tidak ada yang memicu ini otomatis.

## Local Development

### Opsi: `schedule:work` (Paling Praktis)

Buka terminal **terpisah** dari `php artisan serve`, jalankan:

```bash
php artisan schedule:work
```

Perintah ini berjalan di foreground, mengecek dan menjalankan scheduled task setiap menit. Cukup biarkan terminal ini tetap terbuka selama development.

**Verifikasi**: Buka `/pengawas/tokens` — token seharusnya muncul otomatis dalam 1 menit.

> **Catatan**: `schedule:work` hanya untuk development. Jangan gunakan di production.

## Production Deployment

Server production **wajib** punya cron job yang menjalankan scheduler. Tanpa ini, token tidak akan pernah di-generate.

### Linux / macOS

Tambahkan cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Cara setup:
```bash
crontab -e
# Paste baris di atas (sesuaikan /path-to-project)
# Save & exit
```

### Windows Server

Gunakan **Task Scheduler**:
1. Buka Task Scheduler
2. Create Basic Task
3. **Trigger**: Daily, setiap 1 menit (repeat task every 1 minute, duration indefinitely)
4. **Action**: Start a program
   - Program: `C:\path\to\php.exe`
   - Arguments: `artisan schedule:run`
   - Start in: `D:\path\to\project`

### Docker / Container

Jalankan sebagai service terpisah atau tambkan ke entrypoint:

```dockerfile
# Option 1: Separate scheduler container
CMD ["php", "artisan", "schedule:work"]

# Option 2: Cron inside container
RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-scheduler
```

## Commands yang Dijalankan Scheduler

| Command | Frekuensi | Fungsi |
|---------|-----------|--------|
| `tokens:rotate` | everyMinute | Generate token baru per 15 menit untuk setiap ExamPeriod aktif |
| `exam-schedules:sync-status` | everyMinute | Sync status schedule berdasarkan waktu real-time |

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| "Menunggu Token" terus | Jalankan `php artisan schedule:work` di terminal terpisah |
| Token tidak muncul untuk sesi tertentu | Cek `exam_periods`: `start_time` harus <= `now + 5 menit` dan `end_time` > `now` |
| Token expired terus | Normal — token berputar setiap 15 menit. Halaman refresh otomatis via AJAX. |
| Scheduler jalan tapi token kosong | Cek `php artisan tokens:rotate -v` manual untuk lihat error |
