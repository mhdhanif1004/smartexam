<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau Kartu Login - SmartExam</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 24px 16px 48px;
        }
        .page { max-width: 1100px; margin: 0 auto; }

        .preview-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .preview-title { text-align: center; }
        .preview-header .title { font-size: 20px; font-weight: 700; margin: 0; color: #1d4ed8; }
        .preview-header .sub { margin: 2px 0 0; color: #6b7280; font-size: 13px; }
        .preview-header .actions { display: flex; gap: 8px; justify-self: end; }
        .preview-header .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
        }
        .btn-back { background: #fff; color: #374151; border-color: #d1d5db; }
        .btn-back:hover { background: #f9fafb; }
        .btn-print { background: #1d4ed8; color: #fff; }
        .btn-print:hover { background: #1e40af; }

        @media (max-width: 767px) {
            .preview-header {
                grid-template-columns: 1fr;
                justify-items: center;
                row-gap: 16px;
                text-align: center;
            }
            .preview-header .preview-spacer { display: none; }
            .preview-header .actions { justify-self: center; }
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 16px;
        }

        .card {
            border: 1.5px solid #1d4ed8;
            border-radius: 12px;
            background: #fff;
        }
        .card-inner { padding: 14px 16px; }
        .card-head {
            border-bottom: 1.5px dashed #93c5fd;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .brand-row { display: table; width: 100%; }
        .brand-row .brand-logo, .brand-row .brand-mid { display: table-cell; vertical-align: middle; }
        .brand-logo { width: 48px; text-align: left; }
        .brand-logo:last-child { text-align: right; }
        .brand-logo img { height: 44px; width: auto; max-width: 64px; object-fit: contain; }
        .brand-mid { text-align: center; }
        .brand-school { font-size: 15px; font-weight: 700; color: #1d4ed8; line-height: 1.2; }
        .brand-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7280; margin-top: 2px; }

        .card-data { width: 100%; border-collapse: collapse; }
        .card-data td { padding: 5px 0; vertical-align: middle; }
        .card-data .lbl {
            width: 96px;
            font-size: 11px;
            color: #6b7280;
            white-space: nowrap;
        }
        .card-data .val {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            word-break: break-word;
        }
        .card-data .val.password {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            letter-spacing: 0.5px;
            background: #eef2ff;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .card-foot {
            margin-top: 12px;
            border-top: 1.5px dashed #93c5fd;
            padding-top: 10px;
        }
        .card-foot .place-date {
            text-align: right;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .card-foot .sign { text-align: right; }
        .card-foot .sign-title { font-size: 11px; color: #374151; margin-bottom: 2px; }
        .card-foot .sign-name {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            border-bottom: 1.5px solid #111827;
            padding: 0 6px 2px;
            line-height: 1.2;
            margin-top: 22px;
        }
        .card-foot .sign-name.sign-name--long { font-size: 11px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="preview-header">
            <div class="preview-spacer" aria-hidden="true"></div>
            <div class="preview-title">
                <h1 class="title">{{ $setting?->nama_sekolah ?: 'SmartExam' }}</h1>
                <p class="sub">Kartu Login Ujian CBT &middot; {{ count($students) }} peserta &middot; Dicetak {{ $tanggalCetak }}</p>
            </div>
            <div class="actions">
                <a class="btn btn-back" href="{{ route('admin.student-cards.index') }}">Kembali</a>
                <form method="POST" action="{{ route('admin.student-cards.print') }}">
                    @csrf
                    <input type="hidden" name="type" value="peserta">
                    @foreach ($students as $student)
                        <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                    @endforeach
                    <button type="submit" class="btn btn-print">Cetak PDF</button>
                </form>
            </div>
        </div>

        <div class="card-grid">
            @php
                // Logo dibaca sekali, bukan per kartu.
                $logoKiri = $setting?->logoKiriDataUri();
                $logoKanan = $setting?->logoKananDataUri();
            @endphp
            @foreach ($students as $student)
                @include('admin.student-cards.partials.card', [
                    'student' => $student,
                    'setting' => $setting,
                    'tanggalCetak' => $tanggalCetak,
                    'sessionNamesByRoom' => $sessionNamesByRoom,
                    'roomAssignments' => $roomAssignments,
                    'logoKiri' => $logoKiri,
                    'logoKanan' => $logoKanan,
                ])
            @endforeach
        </div>
    </div>
</body>
</html>
