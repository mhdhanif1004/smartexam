<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Subject;
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
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('class_label', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->orderBy('class_label')
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
        Subject::create($request->validated());

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function edit(Subject $subject): View
    {
        return view('admin.subjects.edit', compact('subject'));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil dihapus.');
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
