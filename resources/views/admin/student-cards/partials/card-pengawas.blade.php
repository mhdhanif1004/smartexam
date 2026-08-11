{{--
    Kartu Login Pengawas — markup identik dengan kartu peserta (card.blade.php),
    hanya isi baris data yang berbeda (Nama, Email, Password).
    CSS disediakan oleh template pemanggil (print-pengawas.blade.php untuk A4,
    preview-pengawas.blade.php untuk layar responsif).
    Variabel: $supervisor, $setting, $tanggalCetak (string).
    Opsional: $logoKiri, $logoKanan (data URI) — dihitung otomatis bila tidak dikirim.
--}}
@php
    $schoolName = $setting?->nama_sekolah ?: 'SmartExam';
    $tempat = $setting?->tempat;
    $kepsek = $setting?->nama_kepala_sekolah;
    $jabatan = $setting?->jabatan_kepala_sekolah ?: 'Kepala Sekolah';
    $logoKiri = $logoKiri ?? $setting?->logoKiriDataUri();
    $logoKanan = $logoKanan ?? $setting?->logoKananDataUri();
    $placeDate = $tempat ? rtrim(trim($tempat), ',').', '.$tanggalCetak : $tanggalCetak;

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
                    <div class="brand-title">Kartu Login Pengawas Ujian</div>
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
            <td class="val">{{ $supervisor->user?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Email</td>
            <td class="val">{{ $supervisor->user?->email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Password</td>
            <td class="val password">{{ $supervisor->user?->plain_password ?? '-' }}</td>
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
