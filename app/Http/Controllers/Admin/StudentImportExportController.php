<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentsExport;
use App\Exports\StudentsFailedImportExport;
use App\Exports\StudentsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportStudentsRequest;
use App\Imports\StudentsImport;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class StudentImportExportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $extension = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';

        $query = Student::query()->with(['user', 'room']);

        if ($request->string('scope')->toString() === 'selected') {
            $ids = collect($request->input('ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            $query->whereIn('id', $ids);
        } else {
            $query
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->string('search')->trim();
                    $query->where(function ($builder) use ($search) {
                        $builder->where('nisn', 'like', "%{$search}%")
                            ->orWhere('class_name', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($user) use ($search) {
                                $user->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')));
        }

        $rows = $query
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->get();

        return Excel::download(new StudentsExport($rows), 'data-siswa-'.date('Y-m-d').'.'.$extension);
    }

    public function importTemplate(): BinaryFileResponse
    {
        $classNames = Classroom::query()->orderBy('name')->pluck('name');

        return Excel::download(new StudentsTemplateExport($classNames), 'template-import-siswa.xlsx');
    }

    public function importValidate(ImportStudentsRequest $request): JsonResponse
    {
        $import = new StudentsImport;

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

        // Store in cache with 10-minute TTL (avoid session encryption issues)
        $cacheKey = 'import_pending_'.auth()->id();
        Cache::put($cacheKey, [
            'validRows' => $import->validRows,
            'invalidRows' => $import->invalidRows,
            'newClasses' => $import->newClasses,
            'headerError' => $import->headerError,
        ], now()->addMinutes(10));

        return response()->json([
            'ok' => true,
            'total' => count($import->validRows) + count($import->invalidRows),
            'valid' => count($import->validRows),
            'invalid' => count($import->invalidRows),
            'to_create' => $import->toCreate,
            'to_update' => $import->toUpdate,
            'new_classes_count' => count($import->newClasses),
            'new_classes' => array_values($import->newClasses),
            'errors' => $this->summarizeErrors($import->invalidRows),
        ]);
    }

    public function importConfirm(Request $request): JsonResponse
    {
        $cacheKey = 'import_pending_'.auth()->id();
        $data = Cache::pull($cacheKey);

        if (! is_array($data) || ! isset($data['validRows'])) {
            return response()->json([
                'message' => 'Sesi import kadaluarsa, silakan upload ulang.',
            ], 422);
        }

        // Reconstruct import object for persistRows()
        $import = new StudentsImport;
        $import->validRows = $data['validRows'];
        $import->invalidRows = $data['invalidRows'];
        $import->newClasses = $data['newClasses'] ?? [];

        $result = DB::transaction(function () use ($import): array {
            // Buat kelas baru (yang belum terdaftar di master data) di dalam
            // transaksi yang sama dengan import siswa, supaya kelas baru
            // tidak terlanjur kebuat padahal impornya gagal.
            $newClassesCreated = 0;

            foreach ($import->newClasses as $className) {
                $classroom = Classroom::firstOrCreate(['name' => $className]);

                if ($classroom->wasRecentlyCreated) {
                    $newClassesCreated++;
                }
            }

            return array_merge($import->persistRows(), ['new_classes_created' => $newClassesCreated]);
        });

        $failedFile = null;

        if (! empty($import->invalidRows)) {
            $failedFile = 'data-siswa-gagal-'.date('Y-m-d-His').'.xlsx';
            Excel::store(new StudentsFailedImportExport($import->invalidRows), 'imports/'.$failedFile, 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        $flash = "Import selesai: {$result['created']} siswa baru ditambahkan, {$result['updated']} siswa diperbarui.";

        if ($result['new_classes_created'] > 0) {
            $flash .= " {$result['new_classes_created']} kelas baru ditambahkan (".implode(', ', $import->newClasses).').';
        }

        if ($result['errors']) {
            $flash .= ' '.count($result['errors']).' baris gagal disimpan.';
        }

        session()->flash('success', $flash);

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'new_classes_created' => $result['new_classes_created'],
            'new_classes' => array_values($import->newClasses),
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
