<?php

namespace App\Enums;

/**
 * Daftar aksi audit log — Bahasa Indonesia, snake_case.
 * Nilai disimpan di kolom activity_logs.action; label dipakai untuk dropdown/UI.
 */
enum ActivityAction: string
{
    // 1. Siswa
    case HAPUS_SISWA = 'hapus_siswa';
    case HAPUS_BULK_SISWA = 'hapus_bulk_siswa';
    case UBAH_SISWA_SENSITIF = 'ubah_siswa_sensitif';
    case TAMBAH_SISWA = 'tambah_siswa';

    // 2-4. Admin spesifik
    case LIHAT_PLAIN_PASSWORD = 'lihat_plain_password';
    case TOGGLE_KUNCI_PELANGGARAN = 'toggle_kunci_pelanggaran';
    case AKTIFKAN_SEMESTER = 'aktifkan_semester';

    // 5. Periode ujian (admin)
    case GENERATE_PERIODE_UJIAN = 'generate_periode_ujian';
    case SIMPAN_KELOMPOK_PERIODE = 'simpan_kelompok_periode';
    case ROTASI_PENGAWAS = 'rotasi_pengawas';
    case RESET_PENUGASAN_PENGAWAS = 'reset_penugasan_pengawas';
    case HAPUS_PERIODE_UJIAN = 'hapus_periode_ujian';

    // 6. Soal
    case EDIT_BULK_SOAL = 'edit_bulk_soal';
    case TOGGLE_AKTIF_SOAL = 'toggle_aktif_soal';
    case DUPLIKASI_SOAL = 'duplikasi_soal';
    case HAPUS_BULK_SOAL = 'hapus_bulk_soal';
    case TAMBAH_SOAL = 'tambah_soal';
    case UBAH_SOAL = 'ubah_soal';
    case HAPUS_SOAL = 'hapus_soal';

    // 7. Penugasan guru mapel (admin)
    case PERBARUI_KELAS_AMPU = 'perbarui_kelas_ampu';
    case TAMBAH_PENUGASAN_GURU = 'tambah_penugasan_guru';

    // 7b. Akun Guru Mapel (admin CRUD)
    case TAMBAH_GURU_MAPEL = 'tambah_guru_mapel';
    case UBAH_GURU_MAPEL = 'ubah_guru_mapel';
    case HAPUS_GURU_MAPEL = 'hapus_guru_mapel';
    case HAPUS_BULK_GURU_MAPEL = 'hapus_bulk_guru_mapel';

    // 7c. Akun Pengawas (admin CRUD)
    case TAMBAH_PENGAWAS = 'tambah_pengawas';
    case UBAH_PENGAWAS = 'ubah_pengawas';
    case HAPUS_PENGAWAS = 'hapus_pengawas';
    case HAPUS_BULK_PENGAWAS = 'hapus_bulk_pengawas';

    // 7d. Akun Wali Kelas
    case TAMBAH_WALI_KELAS = 'tambah_wali_kelas';
    case UBAH_WALI_KELAS = 'ubah_wali_kelas';
    case HAPUS_WALI_KELAS = 'hapus_wali_kelas';
    case HAPUS_BULK_WALI_KELAS = 'hapus_bulk_wali_kelas';

    // 7e. Akun Kepala Sekolah
    case TAMBAH_KEPALA_SEKOLAH = 'tambah_kepala_sekolah';
    case UBAH_KEPALA_SEKOLAH = 'ubah_kepala_sekolah';
    case HAPUS_KEPALA_SEKOLAH = 'hapus_kepala_sekolah';
    case HAPUS_BULK_KEPALA_SEKOLAH = 'hapus_bulk_kepala_sekolah';

    // 8. Kehadiran & pelanggaran (pengawas)
    case KONFIRMASI_KEHADIRAN = 'konfirmasi_kehadiran';
    case PERBARUI_KEHADIRAN = 'perbarui_kehadiran';
    case TANGANI_PELANGGARAN = 'tangani_pelanggaran';

    // 9. Nilai (guru mapel)
    case SIMPAN_NILAI = 'simpan_nilai';
    case SIMPAN_SKOR = 'simpan_skor';

    // 10. Nilai sikap & catatan (wali kelas)
    case TAMBAH_NILAI_SIKAP = 'tambah_nilai_sikap';
    case UBAH_NILAI_SIKAP = 'ubah_nilai_sikap';
    case HAPUS_NILAI_SIKAP = 'hapus_nilai_sikap';
    case BULK_NILAI_SIKAP = 'bulk_nilai_sikap';
    case TAMBAH_CATATAN_WALI = 'tambah_catatan_wali';

    // 11. Ujian peserta
    case VALIDASI_TOKEN_UJIAN = 'validasi_token_ujian';
    case KUMPUL_UJIAN = 'kumpul_ujian';
    case LAPOR_PELANGGARAN = 'lapor_pelanggaran';

    // 12. Profil (semua role)
    case PERBARUI_PROFIL = 'perbarui_profil';
    case HAPUS_AKUN = 'hapus_akun';

    // 13. Impor (admin & guru_mapel)
    case IMPOR_DATA = 'impor_data';

    // 14. Master data — Mapel
    case TAMBAH_MAPEL = 'tambah_mapel';
    case UBAH_MAPEL = 'ubah_mapel';
    case HAPUS_MAPEL = 'hapus_mapel';
    case HAPUS_BULK_MAPEL = 'hapus_bulk_mapel';

    // 15. Master data — Ruangan
    case TAMBAH_RUANGAN = 'tambah_ruangan';
    case UBAH_RUANGAN = 'ubah_ruangan';
    case HAPUS_RUANGAN = 'hapus_ruangan';
    case HAPUS_BULK_RUANGAN = 'hapus_bulk_ruangan';

    // 16. Master data — Kelas
    case TAMBAH_KELAS = 'tambah_kelas';
    case UBAH_KELAS = 'ubah_kelas';
    case HAPUS_KELAS = 'hapus_kelas';

    // 17. Master data — Semester
    case TAMBAH_SEMESTER = 'tambah_semester';
    case UBAH_SEMESTER = 'ubah_semester';
    case HAPUS_SEMESTER = 'hapus_semester';

    // 18. Master data — Tahun Ajaran
    case TAMBAH_TAHUN_AJARAN = 'tambah_tahun_ajaran';
    case UBAH_TAHUN_AJARAN = 'ubah_tahun_ajaran';
    case HAPUS_TAHUN_AJARAN = 'hapus_tahun_ajaran';

    // 19. Master data — Jenis Ujian
    case TAMBAH_JENIS_UJIAN = 'tambah_jenis_ujian';
    case UBAH_JENIS_UJIAN = 'ubah_jenis_ujian';
    case HAPUS_JENIS_UJIAN = 'hapus_jenis_ujian';

    // 20. Master data — Jadwal Ujian
    case TAMBAH_JADWAL_UJIAN = 'tambah_jadwal_ujian';
    case UBAH_JADWAL_UJIAN = 'ubah_jadwal_ujian';
    case HAPUS_JADWAL_UJIAN = 'hapus_jadwal_ujian';
    case HAPUS_BULK_JADWAL_UJIAN = 'hapus_bulk_jadwal_ujian';

    /**
     * Daftar nilai string untuk validasi/dropdown.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Label human-readable untuk UI.
     */
    public static function label(string $value): string
    {
        return match ($value) {
            self::HAPUS_SISWA->value => 'Hapus Siswa',
            self::HAPUS_BULK_SISWA->value => 'Hapus Bulk Siswa',
            self::UBAH_SISWA_SENSITIF->value => 'Ubah Data Sensitif Siswa',
            self::TAMBAH_SISWA->value => 'Tambah Siswa',
            self::LIHAT_PLAIN_PASSWORD->value => 'Lihat Plain Password',
            self::TOGGLE_KUNCI_PELANGGARAN->value => 'Kunci/Buka Pelanggaran',
            self::AKTIFKAN_SEMESTER->value => 'Aktifkan Semester',
            self::GENERATE_PERIODE_UJIAN->value => 'Generate Periode Ujian',
            self::SIMPAN_KELOMPOK_PERIODE->value => 'Simpan Kelompok Periode',
            self::ROTASI_PENGAWAS->value => 'Rotasi Pengawas',
            self::RESET_PENUGASAN_PENGAWAS->value => 'Reset Penugasan Pengawas',
            self::HAPUS_PERIODE_UJIAN->value => 'Hapus Periode Ujian',
            self::EDIT_BULK_SOAL->value => 'Edit Bulk Soal',
            self::TOGGLE_AKTIF_SOAL->value => 'Toggle Aktif Soal',
            self::DUPLIKASI_SOAL->value => 'Duplikasi Soal',
            self::HAPUS_BULK_SOAL->value => 'Hapus Bulk Soal',
            self::TAMBAH_SOAL->value => 'Tambah Soal',
            self::UBAH_SOAL->value => 'Ubah Soal',
            self::HAPUS_SOAL->value => 'Hapus Soal',
            self::PERBARUI_KELAS_AMPU->value => 'Perbarui Kelas Ampu',
            self::TAMBAH_PENUGASAN_GURU->value => 'Tambah Penugasan Guru',
            self::TAMBAH_GURU_MAPEL->value => 'Tambah Guru Mapel',
            self::UBAH_GURU_MAPEL->value => 'Ubah Guru Mapel',
            self::HAPUS_GURU_MAPEL->value => 'Hapus Guru Mapel',
            self::HAPUS_BULK_GURU_MAPEL->value => 'Hapus Bulk Guru Mapel',
            self::TAMBAH_PENGAWAS->value => 'Tambah Pengawas',
            self::UBAH_PENGAWAS->value => 'Ubah Pengawas',
            self::HAPUS_PENGAWAS->value => 'Hapus Pengawas',
            self::HAPUS_BULK_PENGAWAS->value => 'Hapus Bulk Pengawas',
            self::TAMBAH_WALI_KELAS->value => 'Tambah Wali Kelas',
            self::UBAH_WALI_KELAS->value => 'Ubah Wali Kelas',
            self::HAPUS_WALI_KELAS->value => 'Hapus Wali Kelas',
            self::HAPUS_BULK_WALI_KELAS->value => 'Hapus Bulk Wali Kelas',
            self::TAMBAH_KEPALA_SEKOLAH->value => 'Tambah Kepala Sekolah',
            self::UBAH_KEPALA_SEKOLAH->value => 'Ubah Kepala Sekolah',
            self::HAPUS_KEPALA_SEKOLAH->value => 'Hapus Kepala Sekolah',
            self::HAPUS_BULK_KEPALA_SEKOLAH->value => 'Hapus Bulk Kepala Sekolah',
            self::KONFIRMASI_KEHADIRAN->value => 'Konfirmasi Kehadiran',
            self::PERBARUI_KEHADIRAN->value => 'Perbarui Kehadiran',
            self::TANGANI_PELANGGARAN->value => 'Tangani Pelanggaran',
            self::SIMPAN_NILAI->value => 'Simpan Nilai',
            self::SIMPAN_SKOR->value => 'Simpan Skor',
            self::TAMBAH_NILAI_SIKAP->value => 'Tambah Nilai Sikap',
            self::UBAH_NILAI_SIKAP->value => 'Ubah Nilai Sikap',
            self::HAPUS_NILAI_SIKAP->value => 'Hapus Nilai Sikap',
            self::BULK_NILAI_SIKAP->value => 'Bulk Nilai Sikap',
            self::TAMBAH_CATATAN_WALI->value => 'Tambah Catatan Wali',
            self::VALIDASI_TOKEN_UJIAN->value => 'Validasi Token Ujian',
            self::KUMPUL_UJIAN->value => 'Kumpul Ujian',
            self::LAPOR_PELANGGARAN->value => 'Lapor Pelanggaran',
            self::PERBARUI_PROFIL->value => 'Perbarui Profil',
            self::HAPUS_AKUN->value => 'Hapus Akun',
            self::IMPOR_DATA->value => 'Impor Data',
            self::TAMBAH_MAPEL->value => 'Tambah Mapel',
            self::UBAH_MAPEL->value => 'Ubah Mapel',
            self::HAPUS_MAPEL->value => 'Hapus Mapel',
            self::HAPUS_BULK_MAPEL->value => 'Hapus Bulk Mapel',
            self::TAMBAH_RUANGAN->value => 'Tambah Ruangan',
            self::UBAH_RUANGAN->value => 'Ubah Ruangan',
            self::HAPUS_RUANGAN->value => 'Hapus Ruangan',
            self::HAPUS_BULK_RUANGAN->value => 'Hapus Bulk Ruangan',
            self::TAMBAH_KELAS->value => 'Tambah Kelas',
            self::UBAH_KELAS->value => 'Ubah Kelas',
            self::HAPUS_KELAS->value => 'Hapus Kelas',
            self::TAMBAH_SEMESTER->value => 'Tambah Semester',
            self::UBAH_SEMESTER->value => 'Ubah Semester',
            self::HAPUS_SEMESTER->value => 'Hapus Semester',
            self::TAMBAH_TAHUN_AJARAN->value => 'Tambah Tahun Ajaran',
            self::UBAH_TAHUN_AJARAN->value => 'Ubah Tahun Ajaran',
            self::HAPUS_TAHUN_AJARAN->value => 'Hapus Tahun Ajaran',
            self::TAMBAH_JENIS_UJIAN->value => 'Tambah Jenis Ujian',
            self::UBAH_JENIS_UJIAN->value => 'Ubah Jenis Ujian',
            self::HAPUS_JENIS_UJIAN->value => 'Hapus Jenis Ujian',
            self::TAMBAH_JADWAL_UJIAN->value => 'Tambah Jadwal Ujian',
            self::UBAH_JADWAL_UJIAN->value => 'Ubah Jadwal Ujian',
            self::HAPUS_JADWAL_UJIAN->value => 'Hapus Jadwal Ujian',
            self::HAPUS_BULK_JADWAL_UJIAN->value => 'Hapus Bulk Jadwal Ujian',
            default => $value,
        };
    }

    /**
     * Kelompok aksi per role untuk filter sidebar/dropdown.
     *
     * @return array<string, string[]>
     */
    public static function groupedByRole(): array
    {
        return [
            'admin' => [
                self::TAMBAH_SISWA->value,
                self::HAPUS_SISWA->value,
                self::HAPUS_BULK_SISWA->value,
                self::UBAH_SISWA_SENSITIF->value,
                self::TAMBAH_PENGAWAS->value,
                self::UBAH_PENGAWAS->value,
                self::HAPUS_PENGAWAS->value,
                self::HAPUS_BULK_PENGAWAS->value,
                self::TAMBAH_GURU_MAPEL->value,
                self::UBAH_GURU_MAPEL->value,
                self::HAPUS_GURU_MAPEL->value,
                self::HAPUS_BULK_GURU_MAPEL->value,
                self::TAMBAH_WALI_KELAS->value,
                self::UBAH_WALI_KELAS->value,
                self::HAPUS_WALI_KELAS->value,
                self::HAPUS_BULK_WALI_KELAS->value,
                self::TAMBAH_KEPALA_SEKOLAH->value,
                self::UBAH_KEPALA_SEKOLAH->value,
                self::HAPUS_KEPALA_SEKOLAH->value,
                self::HAPUS_BULK_KEPALA_SEKOLAH->value,
                self::LIHAT_PLAIN_PASSWORD->value,
                self::TOGGLE_KUNCI_PELANGGARAN->value,
                self::TAMBAH_SEMESTER->value,
                self::UBAH_SEMESTER->value,
                self::HAPUS_SEMESTER->value,
                self::AKTIFKAN_SEMESTER->value,
                self::TAMBAH_TAHUN_AJARAN->value,
                self::UBAH_TAHUN_AJARAN->value,
                self::HAPUS_TAHUN_AJARAN->value,
                self::TAMBAH_MAPEL->value,
                self::UBAH_MAPEL->value,
                self::HAPUS_MAPEL->value,
                self::HAPUS_BULK_MAPEL->value,
                self::TAMBAH_KELAS->value,
                self::UBAH_KELAS->value,
                self::HAPUS_KELAS->value,
                self::TAMBAH_RUANGAN->value,
                self::UBAH_RUANGAN->value,
                self::HAPUS_RUANGAN->value,
                self::HAPUS_BULK_RUANGAN->value,
                self::TAMBAH_JENIS_UJIAN->value,
                self::UBAH_JENIS_UJIAN->value,
                self::HAPUS_JENIS_UJIAN->value,
                self::TAMBAH_JADWAL_UJIAN->value,
                self::UBAH_JADWAL_UJIAN->value,
                self::HAPUS_JADWAL_UJIAN->value,
                self::HAPUS_BULK_JADWAL_UJIAN->value,
                self::GENERATE_PERIODE_UJIAN->value,
                self::SIMPAN_KELOMPOK_PERIODE->value,
                self::ROTASI_PENGAWAS->value,
                self::RESET_PENUGASAN_PENGAWAS->value,
                self::HAPUS_PERIODE_UJIAN->value,
                self::TAMBAH_SOAL->value,
                self::UBAH_SOAL->value,
                self::HAPUS_SOAL->value,
                self::EDIT_BULK_SOAL->value,
                self::TOGGLE_AKTIF_SOAL->value,
                self::DUPLIKASI_SOAL->value,
                self::HAPUS_BULK_SOAL->value,
                self::PERBARUI_KELAS_AMPU->value,
                self::TAMBAH_PENUGASAN_GURU->value,
                self::IMPOR_DATA->value,
            ],
            'pengawas' => [
                self::KONFIRMASI_KEHADIRAN->value,
                self::PERBARUI_KEHADIRAN->value,
                self::TANGANI_PELANGGARAN->value,
            ],
            'guru_mapel' => [
                self::TAMBAH_SOAL->value,
                self::UBAH_SOAL->value,
                self::HAPUS_SOAL->value,
                self::SIMPAN_NILAI->value,
                self::SIMPAN_SKOR->value,
                self::IMPOR_DATA->value,
            ],
            'wali_kelas' => [
                self::TAMBAH_NILAI_SIKAP->value,
                self::UBAH_NILAI_SIKAP->value,
                self::HAPUS_NILAI_SIKAP->value,
                self::BULK_NILAI_SIKAP->value,
                self::TAMBAH_CATATAN_WALI->value,
            ],
            'peserta' => [
                self::VALIDASI_TOKEN_UJIAN->value,
                self::KUMPUL_UJIAN->value,
                self::LAPOR_PELANGGARAN->value,
            ],
            'semua' => [
                self::PERBARUI_PROFIL->value,
                self::HAPUS_AKUN->value,
            ],
        ];
    }
}
