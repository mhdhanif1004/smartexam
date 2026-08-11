<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Login Pengawas - SmartExam</title>
    <style>
        * { box-sizing: border-box; }
        @page {
            size: A4;
            margin: 8mm;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #111827;
            margin: 0;
            font-size: 10px;
        }

        .print-header {
            text-align: center;
            border-bottom: 0.4mm solid #1d4ed8;
            padding-bottom: 2mm;
            margin-bottom: 3mm;
        }
        .print-header h1 { font-size: 15px; margin: 0 0 0.6mm; color: #1d4ed8; }
        .print-header p { margin: 0; color: #4b5563; font-size: 9px; }

        /* 2 kolom × 3 baris per halaman A4.
           190mm lebar konten: 92mm + 5mm gutter + 92mm.
           88mm tinggi baris: kartu 80mm + gap antar baris 8mm.
           nowrap mencegah kartu kedua turun ke baris berikutnya. */
        .card-grid { display: block; }
        .grid-row {
            height: 88mm;
            page-break-inside: avoid;
            white-space: nowrap;
        }

        .card {
            display: inline-block;
            vertical-align: top;
            white-space: normal;
            width: 92mm;
            height: 80mm;
            border: 0.4mm solid #1d4ed8;
            border-radius: 3mm;
            position: relative;
            margin-right: 5mm;
        }
        .grid-row .card + .card { margin-right: 0; }
        .card-inner { padding: 3mm 3mm 0 3mm; }

        /* Header kartu */
        .card-head {
            border-bottom: 0.4mm dashed #60a5fa;
            padding-bottom: 2.4mm;
            margin-bottom: 2.2mm;
        }

        .brand-row { width: 100%; border-collapse: collapse; }
        .brand-row td { vertical-align: middle; }
        .brand-logo { width: 18mm; }
        .brand-logo img { height: 9mm; width: auto; max-width: 14mm; object-fit: contain; }
        .brand-mid { text-align: center; padding: 0 1mm; }
        .brand-school { font-size: 13px; font-weight: bold; color: #1d4ed8; line-height: 1.2; }
        .brand-title { font-size: 7px; text-transform: uppercase; letter-spacing: 0.5mm; color: #6b7280; margin-top: 0.8mm; }

        /* Data kartu */
        .card-data { width: 100%; border-collapse: collapse; }
        .card-data td { padding: 0 0 2.0mm 0; vertical-align: middle; }
        .card-data .lbl { width: 26mm; font-size: 8.5px; color: #4b5563; }
        .card-data .val {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            word-break: break-all;
        }
        .card-data .val.password {
            font-family: 'DejaVu Sans Mono', monospace;
            letter-spacing: 0.3mm;
            word-break: break-all;
        }

        /* Footer kartu: tanggal & tanda tangan di kanan */
        .card-foot {
            position: absolute;
            left: 3mm;
            right: 3mm;
            bottom: 2mm;
        }
        .card-foot .place-date {
            text-align: right;
            font-size: 8px;
            color: #6b7280;
            margin-bottom: 1.4mm;
        }
        .card-foot .sign { text-align: right; padding-top: 1mm; }
        .card-foot .sign-title { font-size: 8px; color: #374151; margin-bottom: 0.4mm; }
        .card-foot .sign-name {
            display: inline-block;
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
            border-bottom: 0.3mm solid #111827;
            padding: 0 1.5mm 0.6mm;
            line-height: 1.1;
            margin-top: 5.5mm;
        }
        .card-foot .sign-name.sign-name--long { font-size: 8.5px; }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>{{ $setting?->nama_sekolah ?: 'SmartExam' }}</h1>
        <p>Kartu Login Pengawas Ujian CBT &middot; {{ $tanggalCetak }} &middot; {{ $supervisors->count() }} pengawas</p>
    </div>
    <div class="card-grid">
        @php
            // Logo dibaca sekali, bukan per kartu (hemat I/O disk & memori PDF).
            $logoKiri = $setting?->logoKiriDataUri();
            $logoKanan = $setting?->logoKananDataUri();
        @endphp
        @forelse ($supervisors as $index => $supervisor)
            @if ($index % 2 === 0)
                <div class="grid-row">
            @endif

            @include('admin.student-cards.partials.card-pengawas', [
                'supervisor' => $supervisor,
                'setting' => $setting,
                'tanggalCetak' => $tanggalCetak,
                'logoKiri' => $logoKiri,
                'logoKanan' => $logoKanan,
            ])

            @if ($index % 2 === 1 || $loop->last)
                </div>
            @endif
        @empty
            <p>Tidak ada data pengawas untuk dicetak.</p>
        @endforelse
    </div>
</body>
</html>
