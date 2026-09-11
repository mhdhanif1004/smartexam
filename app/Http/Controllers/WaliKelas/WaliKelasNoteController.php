<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WaliKelasNote;
use App\Services\WaliKelasDataService;
use App\Traits\ResolvesSelectedSemester;
use App\Traits\ScopesWaliKelas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WaliKelasNoteController extends Controller
{
    use ResolvesSelectedSemester;
    use ScopesWaliKelas;

    /**
     * Halaman Catatan Wali Kelas (list per siswa + form tambah).
     */
    public function index(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');
        $data = app(WaliKelasDataService::class);

        $selectorData = $this->semesterSelectorData();
        $selectedSemesterId = $selectorData['selectedSemesterId'];

        $catatan = $data->catatan($wali->classroom_id, $selectedSemesterId);

        return view('wali_kelas.catatan.index', compact(
            'wali', 'catatan',
        ) + ['semesters' => $selectorData['semesters'], 'selectedSemesterId' => $selectedSemesterId]);
    }

    /**
     * Simpan entri catatan baru (append-only).
     */
    public function store(): RedirectResponse
    {
        $wali = $this->currentWaliKelas();

        $validated = request()->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'tipe' => ['nullable', 'string', 'max:50', 'in:observasi,pelanggaran,prestasi,lainnya'],
            'catatan' => ['required', 'string', 'max:2000'],
        ]);

        $studentId = (int) $validated['student_id'];
        $semesterId = (int) $validated['semester_id'];

        // Pastikan siswa adalah siswa di kelas wali ini sendiri — never
        // terima classroom_id dari request, selalu dari profil wali.
        abort_unless(
            Student::where('id', $studentId)->where('classroom_id', $wali->classroom_id)->exists(),
            403,
            'Siswa tidak termasuk dalam kelas yang Anda ampu.'
        );

        WaliKelasNote::create([
            'student_id' => $studentId,
            'classroom_id' => $wali->classroom_id,
            'wali_kelas_id' => $wali->id,
            'semester_id' => $semesterId,
            'tipe' => $validated['tipe'] ?? null,
            'catatan' => $validated['catatan'],
        ]);

        // Kembali ke halaman Catatan (halaman sendiri, bukan tab lagi),
        // filter siswa tetap terbawa supaya entri baru langsung terlihat.
        return redirect()
            ->route('wali_kelas.catatan', ['student_id' => $studentId])
            ->with('success', 'Catatan berhasil ditambahkan.');
    }
}
