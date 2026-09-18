<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSemesterRequest;
use App\Http\Requests\Admin\UpdateSemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\ActivityLogger;
use App\Services\SemesterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(): View
    {
        $semesters = Semester::query()
            ->with('academicYear')
            ->orderByDesc(
                AcademicYear::query()
                    ->select('nama')
                    ->whereColumn('academic_years.id', 'semesters.academic_year_id')
            )
            ->orderByDesc('jenis')
            ->paginate(10)
            ->withQueryString();

        return view('admin.semesters.index', compact('semesters'));
    }

    public function create(): View
    {
        $academicYears = AcademicYear::query()->orderByDesc('nama')->get();

        return view('admin.semesters.create', compact('academicYears'));
    }

    public function store(StoreSemesterRequest $request): RedirectResponse
    {
        $semester = Semester::create([
            'academic_year_id' => $request->academic_year_id,
            'jenis' => $request->jenis,
        ]);

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_SEMESTER,
            subject: $semester,
            description: "Menambah semester: {$semester->nama_lengkap}",
            properties: ['semester_id' => $semester->id, 'nama_lengkap' => $semester->nama_lengkap],
        );

        return redirect()->route('admin.semesters.index')->with('success', 'Semester berhasil ditambahkan.');
    }

    public function edit(Semester $semester): View
    {
        $semester->load('academicYear');
        $academicYears = AcademicYear::query()->orderByDesc('nama')->get();

        return view('admin.semesters.edit', compact('semester', 'academicYears'));
    }

    public function update(UpdateSemesterRequest $request, Semester $semester): RedirectResponse
    {
        $namaLama = $semester->nama_lengkap;
        $semester->update([
            'academic_year_id' => $request->academic_year_id,
            'jenis' => $request->jenis,
        ]);

        ActivityLogger::log(
            action: ActivityAction::UBAH_SEMESTER,
            subject: $semester,
            description: "Mengubah semester: {$namaLama} -> {$semester->fresh()?->nama_lengkap}",
            properties: ['semester_id' => $semester->id, 'nama_lama' => $namaLama, 'nama_baru' => $semester->fresh()?->nama_lengkap ?? $semester->nama_lengkap],
        );

        return redirect()->route('admin.semesters.index')->with('success', 'Semester berhasil diperbarui.');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        $nama = $semester->nama_lengkap;
        $semesterId = $semester->id;
        $semester->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_SEMESTER,
            description: "Menghapus semester: {$nama}",
            properties: ['semester_id' => $semesterId, 'nama_lengkap' => $nama],
        );

        return redirect()->route('admin.semesters.index')->with('success', 'Semester berhasil dihapus.');
    }

    /**
     * Jadikan semester ini sebagai semester aktif.
     *
     * Memengaruhi seluruh sistem: default semester di semua halaman
     * akan berubah ke semester yang dipilih ini.
     */
    public function makeActive(Semester $semester): RedirectResponse
    {
        $nama = $semester->nama_lengkap;
        $semesterId = $semester->id;

        app(SemesterService::class)->makeActive($semester);

        ActivityLogger::log(
            action: ActivityAction::AKTIFKAN_SEMESTER,
            subject: $semester,
            description: "Mengaktifkan semester {$nama}",
            properties: ['semester_id' => $semesterId, 'nama_lengkap' => $nama],
        );

        return redirect()->route('admin.semesters.index')->with('success', "Semester {$nama} berhasil dijadikan aktif.");
    }
}
