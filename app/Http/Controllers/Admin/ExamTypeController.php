<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamTypeRequest;
use App\Http\Requests\Admin\UpdateExamTypeRequest;
use App\Models\ExamType;
use App\Services\ActivityLogger;
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
        $examType = ExamType::create([
            'name' => $request->name,
            'code' => $request->code,
            'sort_order' => $request->integer('sort_order', 0),
            'boleh_dijadwalkan_guru' => $request->boolean('boleh_dijadwalkan_guru'),
        ]);

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_JENIS_UJIAN,
            subject: $examType,
            description: "Menambah jenis ujian: {$examType->name}",
            properties: ['exam_type_id' => $examType->id, 'nama' => $examType->name, 'kode' => $examType->code],
        );

        return redirect()->route('admin.exam-types.index')->with('success', 'Jenis ujian berhasil ditambahkan.');
    }

    public function edit(ExamType $examType): View
    {
        return view('admin.exam-types.edit', compact('examType'));
    }

    public function update(UpdateExamTypeRequest $request, ExamType $examType): RedirectResponse
    {
        $namaLama = $examType->name;
        $examType->update([
            'name' => $request->name,
            'code' => $request->code,
            'sort_order' => $request->integer('sort_order', $examType->sort_order),
            'boleh_dijadwalkan_guru' => $request->boolean('boleh_dijadwalkan_guru'),
        ]);

        ActivityLogger::log(
            action: ActivityAction::UBAH_JENIS_UJIAN,
            subject: $examType,
            description: "Mengubah jenis ujian: {$namaLama} -> {$examType->name}",
            properties: ['exam_type_id' => $examType->id, 'nama_lama' => $namaLama, 'nama_baru' => $examType->name],
        );

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

        $nama = $examType->name;
        $etId = $examType->id;
        $examType->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_JENIS_UJIAN,
            description: "Menghapus jenis ujian: {$nama}",
            properties: ['exam_type_id' => $etId, 'nama' => $nama],
        );

        return redirect()->route('admin.exam-types.index')->with('success', 'Jenis ujian berhasil dihapus.');
    }
}
