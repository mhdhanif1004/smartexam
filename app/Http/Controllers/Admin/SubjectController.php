<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Enums\ActivityAction;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubjectController extends Controller
{
    private const MAX_BULK_IDS = 500;

    public function index(Request $request): View
    {
        $subjects = Subject::query()
            ->withCount(['questions', 'examSchedules'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function create(): View
    {
        return view('admin.subjects.create');
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_MAPEL,
            subject: $subject,
            description: "Menambah mapel: {$subject->name}",
            properties: ['subject_id' => $subject->id, 'nama' => $subject->name, 'kode' => $subject->code],
        );

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function edit(Subject $subject): View
    {
        return view('admin.subjects.edit', compact('subject'));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $namaLama = $subject->name;
        $kodeLama = $subject->code;
        $subject->update($request->validated());

        ActivityLogger::log(
            action: ActivityAction::UBAH_MAPEL,
            subject: $subject,
            description: "Mengubah mapel: {$namaLama} -> {$subject->name}",
            properties: ['subject_id' => $subject->id, 'nama_lama' => $namaLama, 'nama_baru' => $subject->name, 'kode_lama' => $kodeLama, 'kode_baru' => $subject->code],
        );

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $hasHistory = $subject->examSchedules()->has('examSessions')->exists();

        if ($hasHistory) {
            return back()->with('error', 'Mata pelajaran tidak dapat dihapus karena sudah memiliki histori ujian siswa.');
        }

        $nama = $subject->name;
        $subjectId = $subject->id;
        $kode = $subject->code;
        $subject->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_MAPEL,
            description: "Menghapus mapel: {$nama}",
            properties: ['subject_id' => $subjectId, 'nama' => $nama, 'kode' => $kode],
        );

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil dihapus.');
    }

    /**
     * Preview data terkait subject untuk modal konfirmasi hapus (per-baris).
     */
    public function deletePreview(Subject $subject): JsonResponse
    {
        $questionsCount = $subject->questions()->count();
        $examSchedulesCount = $subject->examSchedules()->count();
        $examSessionsCount = ExamSession::query()
            ->whereHas('examSchedule', fn ($q) => $q->where('subject_id', $subject->id))
            ->count();

        return response()->json([
            'questions_count' => $questionsCount,
            'exam_schedules_count' => $examSchedulesCount,
            'exam_sessions_count' => $examSessionsCount,
        ]);
    }

    /**
     * Update hanya nama mata pelajaran dari modal cepat di halaman Bank Soal.
     */
    public function updateName(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $namaLama = $subject->name;
        $subject->update($data);

        ActivityLogger::log(
            action: ActivityAction::UBAH_MAPEL,
            subject: $subject,
            description: "Mengubah mapel: {$namaLama} -> {$subject->name}",
            properties: ['subject_id' => $subject->id, 'nama_lama' => $namaLama, 'nama_baru' => $subject->name, 'via' => 'updateName'],
        );

        return response()->json(['ok' => true, 'name' => $subject->name]);
    }

    /**
     * Cek relasi untuk peringatan di modal konfirmasi hapus massal.
     */
    public function bulkDeletePreview(Request $request): JsonResponse
    {
        $ids = $this->sanitizeIds($request);

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'Pilih minimal satu mata pelajaran untuk dihapus.'], 422);
        }

        if ($ids->count() > self::MAX_BULK_IDS) {
            return response()->json(['message' => 'Maksimal '.self::MAX_BULK_IDS.' mata pelajaran dalam satu permintaan.'], 422);
        }

        $subjects = Subject::query()
            ->whereIn('id', $ids)
            ->withCount(['questions', 'examSchedules'])
            ->get();

        $linkedCount = $subjects->filter(
            fn (Subject $subject) => $subject->questions_count > 0 || $subject->exam_schedules_count > 0
        )->count();

        return response()->json([
            'total' => $subjects->count(),
            'linked_count' => $linkedCount,
        ]);
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $this->sanitizeIds($request);

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu mata pelajaran untuk dihapus.');
        }

        if ($ids->count() > self::MAX_BULK_IDS) {
            return back()->with('error', 'Maksimal '.self::MAX_BULK_IDS.' mata pelajaran dalam satu permintaan.');
        }

        $deleted = 0;
        $missing = [];

        DB::transaction(function () use ($ids, &$deleted, &$missing) {
            $subjects = Subject::query()->whereIn('id', $ids)->get();

            foreach ($subjects as $subject) {
                $subject->delete();
                $deleted++;
            }

            $missing = $ids->diff($subjects->pluck('id'))->values()->all();
        });

        if ($deleted > 0) {
            ActivityLogger::log(
                action: ActivityAction::HAPUS_BULK_MAPEL,
                description: "Hapus bulk {$deleted} mapel",
                properties: ['jumlah' => $deleted, 'ids' => $ids->values()->all()],
            );
        }

        $message = "{$deleted} mata pelajaran berhasil dihapus.";

        if (count($missing) > 0) {
            return back()
                ->with('success', $message)
                ->with('error', count($missing).' mata pelajaran tidak ditemukan dan tidak ikut dihapus.');
        }

        return back()->with('success', $message);
    }

    /**
     * Bersihkan input ids menjadi koleksi integer unik.
     *
     * @return Collection<int, int>
     */
    private function sanitizeIds(Request $request): Collection
    {
        return collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
