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
                <th style="width:3%">No</th>
                <th style="width:10%">NISN</th>
                <th style="width:20%">Nama Siswa</th>
                <th class="center" style="width:8%">Harian</th>
                <th class="center" style="width:8%">UTS</th>
                <th class="center" style="width:8%">UAS</th>
                <th class="center" style="width:10%">Kehadiran</th>
                <th class="center" style="width:10%">Nilai Akhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $grade)
                @php($bd = $breakdownMap[$grade->student_id] ?? [])
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $grade->student?->nisn ?? '-' }}</td>
                    <td>{{ $grade->student?->user?->name ?? '-' }}</td>
                    <td class="center">{{ isset($bd['harian']) ? number_format($bd['harian']['average'], 2) : '-' }}</td>
                    <td class="center">{{ isset($bd['uts']) ? number_format($bd['uts']['average'], 2) : '-' }}</td>
                    <td class="center">{{ isset($bd['uas']) ? number_format($bd['uas']['average'], 2) : '-' }}</td>
                    <td class="center">{{ isset($bd['kehadiran']) ? number_format($bd['kehadiran']['average'], 2) : '-' }}</td>
                    <td class="center"><strong>{{ number_format((float) $grade->score, 2) }}</strong></td>
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