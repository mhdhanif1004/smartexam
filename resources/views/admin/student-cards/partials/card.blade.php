{{--
    Kartu Login Peserta — markup bersama untuk preview (layar) dan print (PDF).
    Struktur & field TIDAK berubah; CSS disediakan oleh template pemanggil
    (print.blade.php untuk kertas A4, preview.blade.php untuk layar responsif).
    Variabel: $student, $setting, $tanggalCetak (string).
    Opsional: $roomAssignments (peta student_id => koleksi ExamRoomAssignment),
    $logoKiri, $logoKanan (data URI) — biar logo dibaca sekali per render,
    bukan per kartu; dihitung otomatis bila tidak dikirim pemanggil.
--}}
@php
    $schoolName = $setting?->nama_sekolah ?: 'SmartExam';
    $tempat = $setting?->tempat;
    $kepsek = $setting?->nama_kepala_sekolah;
    $jabatan = $setting?->jabatan_kepala_sekolah ?: 'Kepala Sekolah';
    $logoKiri = $logoKiri ?? $setting?->logoKiriDataUri();
    $logoKanan = $logoKanan ?? $setting?->logoKananDataUri();
    $placeDate = $tempat ? rtrim(trim($tempat), ',').', '.$tanggalCetak : $tanggalCetak;

    // Ruangan dan Sesi SELALU dibaca dari exam_room_assignments
    // (satu baris = ruangan + sesi untuk satu siswa). TIDAK ada
    // fallback ke students.room_id atau peta sesi lama agar keduanya
    // konsisten: siswa yang belum diproses "Tambah Kelompok" tampil "-".
    $roomAssignments = $roomAssignments ?? collect();
    $assignments = $roomAssignments[$student->id] ?? collect();

    $sessionNames = $assignments
        ->map(fn ($assignment) => $assignment->examPeriod?->name)
        ->filter()
        ->unique()
        ->values();

    $sessions = $sessionNames->isNotEmpty() ? $sessionNames->implode(', ') : '-';

    $assignmentRooms = $assignments->map(fn ($assignment) => $assignment->room?->display_name ?? '-');

    // Gelar (token berakhiran titik) dilem dengan non-breaking space agar tidak
    // turun ke baris berikutnya; nama tetap bisa wrap di antara kata biasa.
    $nbsp = "\u{00A0}";
    $tokens = $kepsek ? array_values(array_filter(array_map('trim', preg_split('/\s+/u', $kepsek) ?: []), 'strlen')) : [];
    $formattedKepsek = '';
    $prevTitle = false;
    foreach ($tokens as $i => $token) {
        $isTitle = str_contains($token, '.');
        if ($i > 0) {
            $formattedKepsek .= ($prevTitle && $isTitle) ? $nbsp : ' ';
        }
        $formattedKepsek .= $token;
        $prevTitle = $isTitle;
    }
    $nameLong = mb_strlen($formattedKepsek) > 42;
    $jabatanLabel = rtrim($jabatan, ',').',';
@endphp

<div class="card">
    <div class="card-inner">
        <div class="card-head">
        <table class="brand-row">
            <tr>
                <td class="brand-logo">
                    @if ($logoKiri)
                        <img src="{{ $logoKiri }}" alt="Logo Kiri">
                    @endif
                </td>
                <td class="brand-mid">
                    <div class="brand-school">{{ $schoolName }}</div>
                    <div class="brand-title">Kartu Login Ujian</div>
                </td>
                <td class="brand-logo">
                    @if ($logoKanan)
                        <img src="{{ $logoKanan }}" alt="Logo Kanan">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="card-data">
        <tr>
            <td class="lbl">Nama</td>
            <td class="val">{{ $student->user?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">NISN</td>
            <td class="val">{{ $student->nisn }}</td>
        </tr>
        <tr>
            <td class="lbl">Kelas</td>
            <td class="val">{{ $student->class_name }}</td>
        </tr>
        <tr>
            <td class="lbl">Ruangan</td>
            <td class="val">
                @forelse ($assignmentRooms as $assignmentRoom)
                    @unless ($loop->first)<br>@endunless
                    {{ $assignmentRoom }}
                @empty
                    -
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="lbl">Sesi</td>
            <td class="val">{{ $sessions }}</td>
        </tr>
        <tr>
            <td class="lbl">Username</td>
            <td class="val">{{ $student->user?->username ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Password</td>
            <td class="val password">
                @php
                    try {
                        $plainPassword = $student->user?->plain_password ?? '-';
                    } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                        $plainPassword = '-';
                    }
                @endphp
                {{ $plainPassword }}
            </td>
        </tr>
    </table>

    <div class="card-foot">
        <div class="place-date">{{ $placeDate }}</div>
        <div class="sign">
            <div class="sign-title">{{ $jabatanLabel }}</div>
            <div class="sign-name @if ($nameLong) sign-name--long @endif">{{ $formattedKepsek }}</div>
        </div>
    </div>
    </div>
</div>
