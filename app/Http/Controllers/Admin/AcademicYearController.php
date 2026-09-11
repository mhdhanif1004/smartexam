<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Http\Requests\Admin\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        $academicYears = AcademicYear::query()
            ->withCount('semesters')
            ->orderByDesc('nama')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-years.index', compact('academicYears'));
    }

    public function create(): View
    {
        return view('admin.academic-years.create');
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        AcademicYear::create([
            'nama' => $request->nama,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('admin.academic-years.edit', compact('academicYear'));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update([
            'nama' => $request->nama,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    /**
     * Hapus tahun ajaran. DITOLAK jika masih punya semester di bawahnya —
     * bukan cascade diam-diam, supaya admin sadar data semester harus
     * dibereskan dulu.
     */
    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->semesters()->exists()) {
            return redirect()
                ->route('admin.academic-years.index')
                ->with('error', "Tahun ajaran {$academicYear->nama} masih memiliki semester. Hapus semester terlebih dahulu sebelum menghapus tahun ajaran.");
        }

        $academicYear->delete();

        return redirect()->route('admin.academic-years.index')->with('success', 'Tahun ajaran berhasil dihapus.');
    }
}
