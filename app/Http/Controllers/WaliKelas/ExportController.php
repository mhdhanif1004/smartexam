<?php

namespace App\Http\Controllers\WaliKelas;

use App\Exports\WaliKelas\WaliKelasRecapExport;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Violation;
use App\Services\WaliKelasDataService;
use App\Traits\ResolvesSelectedSemester;
use App\Traits\ScopesWaliKelas;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export Excel rekap Wali Kelas (4 sheet, semester aktif dari session).
 */
class ExportController extends Controller
{
    use ResolvesSelectedSemester;
    use ScopesWaliKelas;

    public function exportExcel(): BinaryFileResponse|RedirectResponse
    {
        $wali = $this->currentWaliKelas()->load('classroom');
        $data = app(WaliKelasDataService::class);

        // Defensive: kelas tanpa siswa → tolak export dengan pesan jelas
        // (alih-alih file Excel kosong/corrupt).
        if ($wali->classroom_id === null || ! $wali->classroom?->students()->exists()) {
            return redirect()->back()->with('error', 'Belum ada siswa pada kelas ini. Export rekap dibatalkan.');
        }

        $classroomId = $wali->classroom_id;
        $semesterId = $this->selectedSemesterId();
        $semester = Semester::query()->with('academicYear')->findOrFail($semesterId);

        // Sheet 1: Nilai Akademik (flatten per-siswa → per-mapel, semester dari session)
        $academicRows = collect();
        foreach ($data->academicGrades($classroomId, $semesterId) as $studentData) {
            $student = $studentData['student'];
            foreach ($studentData['grades'] as $gradeData) {
                $academicRows->push([
                    $student->nisn ?? '-',
                    $student->user?->name ?? '-',
                    $gradeData['subject']->name,
                    $gradeData['score'] !== null ? (float) $gradeData['score'] : null,
                    match ($gradeData['source']) {
                        'override' => 'Override Guru',
                        'auto' => 'Terhitung Otomatis',
                        default => '-',
                    },
                ]);
            }
        }

        // Sheet 2: Nilai Sikap — 1 baris per siswa, 1 kolom per aspek.
        $attitudeData = $data->attitudeGrades($classroomId, $semesterId, $wali);
        $aspectNames = $attitudeData['aspects']->pluck('name')->values()->all();
        $attitudeRows = $attitudeData['students']->map(function ($student) use ($attitudeData) {
            $row = [$student->nisn ?? '-', $student->user?->name ?? '-'];
            foreach ($attitudeData['aspects'] as $aspect) {
                $grade = $attitudeData['existingGrades']->get($student->id.'_'.$aspect->id);
                $row[] = $grade?->score;
            }

            return $row;
        });

        // Sheet 3: Rekap Pelanggaran — 1 baris per kejadian.
        $violationRows = $data->violationsDetails($classroomId)->map(function ($violation) {
            $student = $violation->examSession?->student;

            return [
                $student?->nisn ?? '-',
                $student?->user?->name ?? '-',
                Violation::typeLabel($violation->violation_type),
                $violation->occurred_at?->format('d/m/Y') ?? '-',
                $violation->handled_by_supervisor ? 'Ditangani' : 'Belum ditangani',
            ];
        });

        // Sheet 4: Catatan — SEMUA entry, urut kronologis (tertua dulu).
        $catatanRows = $data->catatan($classroomId, $semesterId)['notes']
            ->flatten(1)
            ->sortBy(fn ($note) => $note->created_at)
            ->map(function ($note) {
                return [
                    $note->created_at?->format('d/m/Y H:i') ?? '-',
                    $note->student?->nisn ?? '-',
                    $note->student?->user?->name ?? '-',
                    $note->tipe ? $note->tipeLabel() : '-',
                    $note->catatan,
                ];
            })->values();

        // Nama file: Rekap_{KelasTanpaSpasi}_{TahunAjaran}-{Jenis}.xlsx
        // contoh: Rekap_XIRPL1_2024-2025-Genap.xlsx
        $className = str_replace(' ', '', (string) $wali->classroom?->name);
        $tahunAjaran = str_replace('/', '-', (string) $semester->academicYear?->nama);
        $jenis = $semester->jenis === 'ganjil' ? 'Ganjil' : 'Genap';
        $filename = "Rekap_{$className}_{$tahunAjaran}-{$jenis}.xlsx";

        $export = new WaliKelasRecapExport([
            'academicGrades' => $academicRows,
            'attitudeGrades' => $attitudeRows,
            'violations' => $violationRows,
            'catatan' => $catatanRows,
        ], $aspectNames);

        return Excel::download($export, $filename);
    }
}
