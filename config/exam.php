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
    */

    'attendance_tolerance_minutes' => (int) env('EXAM_ATTENDANCE_TOLERANCE_MINUTES', 10),

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
