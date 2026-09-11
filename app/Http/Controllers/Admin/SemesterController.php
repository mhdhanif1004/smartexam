<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSemesterRequest;
use App\Http\Requests\Admin\UpdateSemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
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
        Semester::create([
            'academic_year_id' => $request->academic_year_id,
            'jenis' => $request->jenis,
        ]);

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
        $semester->update([
            'academic_year_id' => $request->academic_year_id,
            'jenis' => $request->jenis,
        ]);

        return redirect()->route('admin.semesters.index')->with('success', 'Semester berhasil diperbarui.');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        $semester->delete();

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
        app(SemesterService::class)->makeActive($semester);

        return redirect()->route('admin.semesters.index')->with('success', "Semester {$semester->nama_lengkap} berhasil dijadikan aktif.");
    }
}
