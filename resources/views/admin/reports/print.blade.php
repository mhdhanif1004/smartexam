<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Hasil Ujian - SmartExam</title>
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
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: center;
            font-size: 10px;
        }
        .summary .num { font-size: 13px; font-weight: bold; color: #1d4ed8; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            font-size: 9.5px;
        }
        table.data th { background: #eef2ff; }
        table.data .center { text-align: center; }
        .footer { margin-top: 12px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SmartExam - Laporan Hasil Ujian</h1>
        <p>
            Dicetak {{ now()->format('d M Y H:i') }}
            @if ($filters['subject_id']) &middot; Mapel: {{ $rows->first()?->examSession?->examSchedule?->subject?->name ?? $filters['subject_id'] }} @endif
            @if ($filters['class_name']) &middot; Kelas: {{ $filters['class_name'] }} @endif
            @if ($filters['date_from']) &middot; Dari: {{ $filters['date_from'] }} @endif
            @if ($filters['date_to']) &middot; Sampai: {{ $filters['date_to'] }} @endif
        </p>
    </div>

    <table class="summary">
        <tr>
            <td>Total<br><span class="num">{{ $summary['total'] }}</span></td>
            <td>Rata-rata<br><span class="num">{{ number_format($summary['average'], 2) }}</span></td>
            <td>Tertinggi<br><span class="num">{{ number_format($summary['highest'], 2) }}</span></td>
            <td>Terendah<br><span class="num">{{ number_format($summary['lowest'], 2) }}</span></td>
            <td>Lulus<br><span class="num">{{ $summary['passed'] }}</span></td>
            <td>Tidak Lulus<br><span class="num">{{ $summary['failed'] }}</span></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:11%">NISN</th>
                <th style="width:20%">Nama Siswa</th>
                <th style="width:10%">Kelas</th>
                <th style="width:20%">Mata Pelajaran</th>
                <th style="width:12%">Tanggal</th>
                <th class="center" style="width:8%">Nilai</th>
                <th class="center" style="width:9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $result)
                @php
                    $student = $result->examSession?->student;
                    $schedule = $result->examSession?->examSchedule;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $student?->nisn ?? '-' }}</td>
                    <td>{{ $student?->user?->name ?? '-' }}</td>
                    <td>{{ $student?->class_name ?? '-' }}</td>
                    <td>{{ $schedule?->subject?->name ?? '-' }}</td>
                    <td>{{ $schedule?->exam_date?->format('d/m/Y') ?? '-' }}</td>
                    <td class="center">{{ $result->total_score ?? '-' }}</td>
                    <td class="center">{{ $result->is_passed ? 'Lulus' : 'Tidak Lulus' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">Tidak ada data hasil ujian.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Laporan ini dihasilkan otomatis oleh Sistem CBT SmartExam.</div>
</body>
</html>
