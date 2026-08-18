<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SupervisorsExport;
use App\Exports\SupervisorsFailedImportExport;
use App\Exports\SupervisorsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSupervisorsRequest;
use App\Imports\SupervisorsImport;
use App\Models\Room;
use App\Models\Supervisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SupervisorImportExportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $extension = $request->string('format')->toString() === 'csv' ? 'csv' : 'xlsx';

        $query = Supervisor::query()->with(['user', 'room', 'roomAssignments.room']);

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
                        $builder->whereHas('user', function ($user) use ($search) {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })->orWhereHas('room', function ($room) use ($search) {
                            $room->where('name', 'like', "%{$search}%");
                        });
                    });
                })
                ->when($request->filled('room'), fn ($query) => $query->where('room_id', $request->integer('room')));
        }

        $rows = $query
            ->orderBy(Room::query()->select('room_number')->whereColumn('rooms.id', 'supervisors.room_id'))
            ->orderBy('user_id')
            ->get();

        return Excel::download(new SupervisorsExport($rows), 'data-pengawas-'.date('Y-m-d').'.'.$extension);
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new SupervisorsTemplateExport, 'template-import-pengawas.xlsx');
    }

    public function importValidate(ImportSupervisorsRequest $request): JsonResponse
    {
        $import = new SupervisorsImport;

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
            'headerError' => $import->headerError,
        ], now()->addMinutes(10));

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
        $cacheKey = 'import_pending_'.auth()->id();
        $data = Cache::pull($cacheKey);

        if (! is_array($data) || ! isset($data['validRows'])) {
            return response()->json([
                'message' => 'Sesi import kadaluarsa, silakan upload ulang.',
            ], 422);
        }

        // Reconstruct import object for persistRows()
        $import = new SupervisorsImport;
        $import->validRows = $data['validRows'];
        $import->invalidRows = $data['invalidRows'];

        $result = $import->persistRows();

        $failedFile = null;

        if (! empty($import->invalidRows)) {
            $failedFile = 'data-pengawas-gagal-'.date('Y-m-d-His').'.xlsx';
            Excel::store(new SupervisorsFailedImportExport($import->invalidRows), 'imports/'.$failedFile, 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        $flash = "Import selesai: {$result['created']} pengawas baru ditambahkan, {$result['updated']} pengawas diperbarui.";

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
