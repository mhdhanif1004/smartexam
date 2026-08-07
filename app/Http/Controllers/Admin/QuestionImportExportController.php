<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QuestionsExport;
use App\Exports\QuestionsFailedImportExport;
use App\Exports\QuestionsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Imports\QuestionsImport;
use App\Models\Question;
use App\Models\Subject;
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

        $query = Question::query()->with('subject');

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

    public function importTemplate(): BinaryFileResponse
    {
        $subjects = Subject::query()->orderBy('name')->get();

        return Excel::download(new QuestionsTemplateExport($subjects), 'template-import-soal.xlsx');
    }

    public function importValidate(ImportQuestionsRequest $request): JsonResponse
    {
        $import = new QuestionsImport;

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

        if (! $import instanceof QuestionsImport) {
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

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed_count' => count($import->invalidRows),
            'failed_file' => $failedFile,
            'errors' => $result['errors'],
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
