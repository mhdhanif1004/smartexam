<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Login Peserta - SmartExam</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .print-header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
        }
        .print-header h1 { font-size: 18px; margin: 0; color: #1d4ed8; }
        .print-header p { margin: 2px 0 0; color: #4b5563; font-size: 11px; }
        .card-grid { text-align: left; }
        .card {
            display: inline-block;
            vertical-align: top;
            width: 32.2%;
            min-height: 118px;
            margin: 0 0.5% 12px 0;
            border: 1.5px solid #1d4ed8;
            border-radius: 10px;
            padding: 10px 12px;
            page-break-inside: avoid;
        }
        .card-head {
            border-bottom: 1px dashed #93c5fd;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .card-head .brand-row {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .card-head .brand-row img {
            height: 16px;
            width: auto;
        }
        .card-head .brand {
            font-size: 13px;
            font-weight: bold;
            color: #1d4ed8;
        }
        .card-head .title {
            margin-top: 1px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }
        .card-row {
            margin-bottom: 3px;
            font-size: 11px;
            line-height: 1.35;
        }
        .card-row .label {
            display: inline-block;
            width: 62px;
            color: #6b7280;
            font-size: 10px;
        }
        .card-row .value {
            font-weight: bold;
            color: #111827;
        }
        .card-row .value.password {
            font-family: 'DejaVu Sans Mono', monospace;
            letter-spacing: 0.5px;
            background: #eef2ff;
            padding: 1px 5px;
            border-radius: 3px;
        }
        .card-foot {
            margin-top: 7px;
            border-top: 1px dashed #93c5fd;
            padding-top: 5px;
            font-size: 8.5px;
            color: #6b7280;
        }
        .note {
            margin-top: 14px;
            font-size: 9.5px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>SmartExam</h1>
        <p>Kartu Login Peserta Ujian CBT &middot; Dicetak {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="card-grid">
        @php
            $logoData = base64_encode(file_get_contents(public_path('images/logo.jpg')));
        @endphp
        @forelse ($students as $student)
            <div class="card">
                <div class="card-head">
                    <div class="brand-row">
                        <img src="data:image/jpeg;base64,{{ $logoData }}" alt="Logo Sekolah">
                        <span class="brand">SmartExam</span>
                    </div>
                    <div class="title">Kartu Login Peserta Ujian</div>
                </div>
                <div class="card-row">
                    <span class="label">Nama</span>
                    <span class="value">{{ $student->user?->name ?? '-' }}</span>
                </div>
                <div class="card-row">
                    <span class="label">NISN</span>
                    <span class="value">{{ $student->nisn }}</span>
                </div>
                <div class="card-row">
                    <span class="label">Kelas</span>
                    <span class="value">{{ $student->class_name }}</span>
                </div>
                <div class="card-row">
                    <span class="label">Username</span>
                    <span class="value">{{ $student->user?->username ?? '-' }}</span>
                </div>
                <div class="card-row">
                    <span class="label">Password</span>
                    <span class="value password">{{ $student->user?->plain_password ?? '-' }}</span>
                </div>
                <div class="card-foot">Simpan kartu ini dengan aman. Jangan tunjukkan password kepada orang lain.</div>
            </div>
        @empty
            <p>Tidak ada data siswa untuk dicetak.</p>
        @endforelse
    </div>

    <div class="note">
        Kartu ini berisi kredensial login sistem ujian SmartExam. Harap diserahkan kepada peserta yang bersangkutan dan diminta untuk segera mengganti password setelah login.
    </div>
</body>
</html>
