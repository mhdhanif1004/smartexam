<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GuruMapelsFailedImportExport;
use App\Exports\GuruMapelsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportGuruMapelsRequest;
use App\Imports\GuruMapelsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GuruMapelImportExportController extends Controller
{
    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new GuruMapelsTemplateExport, 'template-import-guru-mapel.xlsx');
    }

    public function importValidate(ImportGuruMapelsRequest $request): JsonResponse
    {
        $import = new GuruMapelsImport;

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

        $cacheKey = 'guru_mapels_import_pending_'.auth()->id();
        Cache::put($cacheKey, [
            'validRows' => $import->validRows,
            'invalidRows' => $import->invalidRows,
            'headerError' => $import->headerError,
        ], now()->addMinutes(10));

        return response()->json([
            'ok' => true,
            'total' => count($import->validRows) + count($import->invalidRows),
            'valid' => count($import->validRows),
            'invalid' => count($import->invalidRows),
            'to_create' => count($import->validRows),
            'to_update' => 0,
            'assignments' => $this->countAssignments($import->validRows),
            'errors' => $this->summarizeErrors($import->invalidRows),
        ]);
    }

    public function importConfirm(Request $request): JsonResponse
    {
        $cacheKey = 'guru_mapels_import_pending_'.auth()->id();
        $data = Cache::pull($cacheKey);

        if (! is_array($data) || ! isset($data['validRows'])) {
            return response()->json([
                'message' => 'Sesi import kadaluarsa, silakan upload ulang.',
            ], 422);
        }

        $import = new GuruMapelsImport;
        $import->validRows = $data['validRows'];
        $import->invalidRows = $data['invalidRows'];

        $result = $import->persistRows();

        $failedFile = null;

        if (! empty($import->invalidRows)) {
            $failedFile = 'data-guru-mapel-gagal-'.date('Y-m-d-His').'.xlsx';
            Excel::store(new GuruMapelsFailedImportExport($import->invalidRows), 'imports/'.$failedFile, 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        $flash = "Import selesai: {$result['created']} guru mapel baru ditambahkan,"
            ." {$result['updated']} guru diperbarui, {$result['assignments']} penugasan (guru-mapel-kelas) tersimpan.";

        if ($result['errors']) {
            $flash .= ' '.count($result['errors']).' baris gagal disimpan.';
        }

        session()->flash('success', $flash);

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'assignments' => $result['assignments'],
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
     * @param  list<array{row:int, nama:string, email:string, subject_id:int, classroom_ids:list<int>}>  $validRows
     */
    private function countAssignments(array $validRows): int
    {
        return array_reduce($validRows, fn (int $carry, array $row) => $carry + count($row['classroom_ids']), 0);
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
