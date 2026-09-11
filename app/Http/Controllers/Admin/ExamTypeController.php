<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamTypeRequest;
use App\Http\Requests\Admin\UpdateExamTypeRequest;
use App\Models\ExamType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamTypeController extends Controller
{
    public function index(): View
    {
        $examTypes = ExamType::query()
            ->orderBy('sort_order')
            ->paginate(10)
            ->withQueryString();

        return view('admin.exam-types.index', compact('examTypes'));
    }

    public function create(): View
    {
        return view('admin.exam-types.create');
    }

    public function store(StoreExamTypeRequest $request): RedirectResponse
    {
        ExamType::create([
            'name' => $request->name,
            'code' => $request->code,
            'sort_order' => $request->integer('sort_order', 0),
            'boleh_dijadwalkan_guru' => $request->boolean('boleh_dijadwalkan_guru'),
        ]);

        return redirect()->route('admin.exam-types.index')->with('success', 'Jenis ujian berhasil ditambahkan.');
    }

    public function edit(ExamType $examType): View
    {
        return view('admin.exam-types.edit', compact('examType'));
    }

    public function update(UpdateExamTypeRequest $request, ExamType $examType): RedirectResponse
    {
        $examType->update([
            'name' => $request->name,
            'code' => $request->code,
            'sort_order' => $request->integer('sort_order', $examType->sort_order),
            'boleh_dijadwalkan_guru' => $request->boolean('boleh_dijadwalkan_guru'),
        ]);

        return redirect()->route('admin.exam-types.index')->with('success', 'Jenis ujian berhasil diperbarui.');
    }

    public function destroy(ExamType $examType): RedirectResponse
    {
        // Tolak jika masih ada ExamPeriod yang menggunakan jenis ujian ini.
        if ($examType->schedules()->exists()) {
            return redirect()
                ->route('admin.exam-types.index')
                ->with('error', "Jenis ujian \"{$examType->name}\" masih digunakan oleh sesi ujian. Tidak dapat dihapus.");
        }

        $examType->delete();

        return redirect()->route('admin.exam-types.index')->with('success', 'Jenis ujian berhasil dihapus.');
    }
}
