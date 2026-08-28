<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Nilai - SmartExam</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 18px;
            font-size: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header h1 { font-size: 16px; margin: 0; color: #1d4ed8; }
        .header p { margin: 2px 0 0; color: #4b5563; font-size: 10px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            font-size: 9.5px;
        }
        table.data th { background: #eef2ff; }
        table.data .center { text-align: center; }
        table.data .right { text-align: right; }
        .footer { margin-top: 12px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SmartExam - Daftar Nilai</h1>
        <p>
            Dicetak {{ now()->format('d M Y H:i') }}
            @if ($subject) &middot; Mapel: {{ $subject->name }} @endif
            @if ($classroom) &middot; Kelas: {{ $classroom->name }} @endif
        </p>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:11%">NISN</th>
                <th style="width:22%">Nama Siswa</th>
                <th style="width:12%">Kelas</th>
                <th style="width:12%">Jenis Nilai</th>
                <th style="width:18%">Judul</th>
                <th class="right" style="width:8%">Skor</th>
                <th class="center" style="width:10%">Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $grade)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $grade->student?->nisn ?? '-' }}</td>
                    <td>{{ $grade->student?->user?->name ?? '-' }}</td>
                    <td>{{ $grade->classroom?->name ?? '-' }}</td>
                    <td>{{ \App\Models\Grade::TYPES[$grade->grade_type] ?? $grade->grade_type }}</td>
                    <td>{{ $grade->title ?: '-' }}</td>
                    <td class="right">{{ number_format((float) $grade->score, 2) }}</td>
                    <td class="center">{{ $grade->created_at?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">Tidak ada data nilai.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dokumen ini dihasilkan otomatis oleh Sistem CBT SmartExam.</div>
</body>
</html>
