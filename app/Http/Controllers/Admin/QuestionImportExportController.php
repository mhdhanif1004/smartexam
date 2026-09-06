<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QuestionsExport;
use App\Exports\QuestionsFailedImportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Imports\Questions\BaseTypeImport;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Subject;
use App\Services\QuestionWeightService;
use App\Support\QuestionImportMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class QuestionImportExportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $extension = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';

        $query = Question::query()->with(['subject', 'classrooms']);

        if ($request->string('scope')->toString() === 'selected') {
            $ids = collect($request->input('ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            $query->whereIn('id', $ids);
        } else {
            $query
                ->when($request->filled('search'), fn ($query) => $query->where('question_text', 'like', '%'.$request->string('search')->trim().'%'))
                ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
                ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
                ->when($request->filled('status'), function ($query) use ($request) {
                    $request->string('status') === 'aktif' ? $query->where('is_active', true) : $query->where('is_active', false);
                });
        }

        $rows = $query->orderByDesc('id')->get();

        return Excel::download(new QuestionsExport($rows), 'bank-soal-'.date('Y-m-d').'.'.$extension);
    }

    public function importTemplate(string $type): BinaryFileResponse
    {
        $template = QuestionImportMap::templates()[$type] ?? null;

        if ($template === null) {
            abort(404);
        }

        $subjects = Subject::query()->orderBy('name')->get();

        return Excel::download(new $template['export']($subjects), $template['file']);
    }

    public function importValidate(ImportQuestionsRequest $request): JsonResponse
    {
        $template = QuestionImportMap::templates()[$request->string('type')->toString()] ?? null;

        if ($template === null) {
            return response()->json([
                'message' => 'Jenis soal tidak valid.',
            ], 422);
        }

        /** @var BaseTypeImport $import */
        $import = new $template['import'];
        $import->applyClassroomIds = $request->validated()['classroom_ids'];

        try {
            Excel::import($import, $request->file('file'));
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Gagal membaca file, pastikan format sesuai template.',
            ], 422);
        }

        if ($import->headerError !== '') {
            return response()->json([
                'message' => $import->headerError,
            ], 422);
        }

        session()->put('questions_import_pending', $import);

        return response()->json([
            'ok' => true,
            'type' => $import->type(),
            'type_label' => $template['label'],
            'total' => count($import->validRows) + count($import->invalidRows),
            'valid' => count($import->validRows),
            'invalid' => count($import->invalidRows),
            'to_create' => $import->toCreate,
            'to_update' => $import->toUpdate,
            'errors' => $this->summarizeErrors($import->invalidRows),
        ]);
    }

    public function importConfirm(Request $request): JsonResponse
    {
        $import = session()->pull('questions_import_pending');

        if (! $import instanceof BaseTypeImport) {
            return response()->json([
                'message' => 'Sesi import kadaluarsa, silakan upload ulang.',
            ], 422);
        }

        $result = DB::transaction(fn () => $import->persistRows());

        $failedFile = null;

        if (! empty($import->invalidRows)) {
            $failedFile = 'data-soal-gagal-'.date('Y-m-d-His').'.xlsx';
            Excel::store(new QuestionsFailedImportExport($import->invalidRows), 'imports/'.$failedFile, 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        $flash = "Import selesai: {$result['created']} soal baru ditambahkan.";

        if ($result['errors']) {
            $flash .= ' '.count($result['errors']).' baris gagal disimpan.';
        }

        session()->flash('success', $flash);

        // Warning non-blocking bila total bobot per (subject×classroom) terdampak != 100
        $pairs = [];
        foreach ($import->validRows as $row) {
            foreach ($import->applyClassroomIds as $cid) {
                $pairs[] = [(int) $row['subject_id'], (int) $cid];
            }
        }
        $pairs = collect($pairs)->unique(fn ($p) => $p[0].':'.$p[1])->values()->all();
        $warning = $this->weightWarningForPairs($pairs);
        if ($warning !== null) {
            session()->flash('warning', $warning);
        }

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed_count' => count($import->invalidRows),
            'failed_file' => $failedFile,
            'errors' => $result['errors'],
            'warning' => $warning,
        ]);
    }

    public function importFailed(string $file): BinaryFileResponse
    {
        $file = basename($file);

        if ($file === '' || ! Storage::disk('local')->exists('imports/'.$file)) {
            abort(404, 'File tidak ditemukan.');
        }

        return response()->download(Storage::disk('local')->path('imports/'.$file), $file);
    }

    private function weightWarningForPairs(array $pairs): ?string
    {
        if ($pairs === []) {
            return null;
        }
        $service = new QuestionWeightService;
        $subjectIds = collect($pairs)->pluck(0)->unique()->values()->all();
        $classroomIds = collect($pairs)->pluck(1)->unique()->values()->all();
        $subjectMap = Subject::query()->whereIn('id', $subjectIds)->pluck('name', 'id');
        $classroomMap = Classroom::query()->whereIn('id', $classroomIds)->pluck('name', 'id');
        $msgs = [];
        foreach ($pairs as [$sid, $cid]) {
            $result = $service->check((int) $sid, (int) $cid);
            if ($result['status'] === 'ok') {
                continue;
            }
            $subjectName = $subjectMap->get($sid, "Mapel #{$sid}");
            $kelas = $classroomMap->get($cid, "Kelas #{$cid}");
            $total = number_format($result['total'], 2, ',', '.');
            $delta = number_format($result['delta'], 2, ',', '.');
            $arah = $result['status'] === 'over' ? "kelebihan {$delta}" : "kekurangan {$delta}";
            $msgs[] = "{$kelas} × {$subjectName}: total {$total} (harus 100, {$arah})";
        }
        if ($msgs === []) {
            return null;
        }

        return 'Perhatian bobot: '.implode('; ', array_slice($msgs, 0, 5)).(count($msgs) > 5 ? ' dan '.(count($msgs) - 5).' lainnya' : '').'. Perbaiki bobot di Bank Soal.';
    }

    /**
     * @param  list<array{row: int, data: array<string, mixed>, errors: list<string>}>  $invalidRows
     * @return list<string>
     */
    private function summarizeErrors(array $invalidRows): array
    {
        return array_map(function (array $invalidRow): string {
            return 'Baris '.$invalidRow['row'].': '.implode(' ', $invalidRow['errors']);
        }, $invalidRows);
    }
}
