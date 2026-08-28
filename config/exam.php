<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pengaturan Ujian
    |--------------------------------------------------------------------------
    |
    | attendance_tolerance_minutes: jendela absensi pengawas (termasuk absensi
    | ulang peserta yang dinonaktifkan karena pelanggaran) tetap terbuka sampai
    | N menit SETELAH waktu ujian selesai, untuk mengakomodasi peserta selama
    | jeda antar sesi. Setelah lewat toleransi ini, jendela ditutup total dan
    | pengawas tidak bisa lagi konfirmasi absensi untuk sesi tersebut.
    |
    | grace_period_minutes: toleransi waktu tambahan setelah Timer Sesi
    | (period->end_time) habis. Selama masa ini siswa masih bisa melanjutkan
    | mengerjakan. Setelah grace habis, auto-submit paksa terjadi.
    |
    */

    'attendance_tolerance_minutes' => (int) env('EXAM_ATTENDANCE_TOLERANCE_MINUTES', 10),

    'grace_period_minutes' => (int) env('EXAM_GRACE_PERIOD_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Sesi Ujian Macet (sessions:cleanup-stuck)
    |--------------------------------------------------------------------------
    |
    | cleanup_heartbeat_interval_seconds: jeda minimal antar penulisan kolom
    | last_activity_at (detak jantung) agar polling tiap 10 detik tidak
    | membebani tulis DB. Default 60 detik; tetap cukup akurat untuk kriteria
    | stuck 30 menit.
    |
    | cleanup_grace_minutes: berapa lama sejak last_activity_at terakhir sesi
    | dianggap "stuck" (tidak ada aktivitas sama sekali) sehingga di-cleanup
    | menjadi status timed_out. Diambil dari jejak terakhir siswa aktif,
    | terlepas dari jadwal mapel/period.
    |
    */

    'cleanup_heartbeat_interval_seconds' => (int) env('EXAM_CLEANUP_HEARTBEAT_INTERVAL_SECONDS', 60),

    'cleanup_grace_minutes' => (int) env('EXAM_CLEANUP_GRACE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Batas Maksimal Pengawas per Ruangan
    |--------------------------------------------------------------------------
    |
    | Nilai tertinggi yang boleh dipilih untuk kolom "Jumlah Pengawas" pada
    | ruangan (supervisor_count). Dipakai untuk validasi form ruangan, pilihan
    | di form, dan pembatas saat algoritma rotasi menghitung kebutuhan slot
    | pengawas tiap ruangan.
    |
    */

    'max_supervisors_per_room' => (int) env('EXAM_MAX_SUPERVISORS_PER_ROOM', 3),
];
