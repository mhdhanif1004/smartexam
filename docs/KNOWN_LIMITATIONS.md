# Known Limitations

Dokumentasi batasan yang diketahui dan memerlukan perhatian di masa mendatang.

---

## 1. Stuck IN_PROGRESS Session (Deadlock Early-Start)

### Masalah

Jika siswa membuka token untuk sebuah mapel (session menjadi `IN_PROGRESS`), lalu browser crash atau ditutup tanpa melakukan submit, session tersebut **tetap `IN_PROGRESS` selamanya** — tidak ada mekanisme server-side yang otomatis membersihkannya.

### Dampak

Sistem early-start (siswa boleh langsung lanjut ke mapel berikutnya dalam sesi yang sama setelah menyelesaikan satu mapel) menggunakan guard `hasActiveSessionInPeriod()` yang memeriksa apakah ada session `IN_PROGRESS` lain di period yang sama. Session stuck ini akan:

1. **Block early-start ke semua mapel lain** di period yang sama — `hasActiveSessionInPeriod()` return `true`
2. **Memblokir sampai intervensi manual** — session tidak bisa di-completed tanpa akses browser ke halaman ujian

### Kapan Terjadi

- Browser crash / force close saat siswa sedang mengerjakan
- Koneksi internet putus di tengah ujian (tanpa auto-submit)
- Siswa menutup tab browser tanpa submit

### Solusi Potensial (TODO)

Buat Artisan command `sessions:cleanup-stuck` yang:

1. Cari session `IN_PROGRESS` di mana `started_at + duration_minutes + tolerance < now()`
2. Auto-submit session tersebut (kosongkan jawaban, finalize dengan nol benar)
3. Atau minimal ubah status ke `timed_out` / `completed` dengan flag khusus

```php
// Contoh logika (belum diimplementasikan)
ExamSession::query()
    ->where('status', 'in_progress')
    ->whereHas('examSchedule', function ($q) {
        $q->whereRaw('started_at + INTERVAL duration_minutes MINUTE + INTERVAL 10 MINUTE < NOW()');
    })
    ->each(fn ($session) => $grading->finalize($session, $session->examSchedule));
```

**Tambahan**: tambahkan kolom `status` baru `'timed_out'` di `ExamSession` untuk membedakan auto-submit dari submit manual siswa.

### Mitigasi Sementara

Saat ini satu-satunya solusi adalah **intervensi manual** oleh admin atau pengawas:
- Admin bisa unlock session via panel admin (`locked_by_admin = true`)
- Pengawas bisa membuat session baru melalui flow absensi
- Atau langsung update database: ubah status session stuck ke `completed`

---

## 2. Dual Timer — Timer Mapel Bisa Melewati Timer Sesi

### Masalah

Timer JS menampilkan 2 timer: Timer Mapel (`started_at + duration_minutes`) dan Timer Sesi (`period.end_time - now()`). Timer Mapel bisa saja melewati Timer Sesi jika siswa memulai pengerjaan mendekati akhir sesi.

### Dampak

Kosmetik saja. Auto-submit tetap menggunakan server-authoritative deadline (kombinasi `started_at + duration_minutes` dan `period.end_time`), jadi tidak ada masalah fungsional. Timer Mapel hanya berfungsi sebagai panduan visual.

### Status

**Tidak perlu diperbaiki** — behavior ini sudah benar secara desain.
