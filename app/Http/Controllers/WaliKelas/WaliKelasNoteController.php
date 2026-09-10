<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WaliKelasNote;
use App\Traits\ScopesWaliKelas;
use Illuminate\Http\RedirectResponse;

class WaliKelasNoteController extends Controller
{
    use ScopesWaliKelas;

    /**
     * Simpan entri catatan baru (append-only).
     *
     * List/show catatan ditangani inline oleh tab 'catatan' pada dashboard
     * Wali Kelas (lihat WaliKelasDashboardController::getCatatan) — endpoint
     * ini hanya bertugas menerima POST dari form "Tambah Catatan Baru".
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

        // Kembali ke dashboard dengan tab 'catatan' tetap aktif agar user
        // tidak "kehilangan tempat" setelah submit, dan semester ikut
        // dipertahankan supaya entri baru langsung terlihat.
        return redirect()
            ->route('wali_kelas.dashboard', [
                'tab' => 'catatan',
                'semester_id' => $semesterId,
            ])
            ->with('success', 'Catatan berhasil ditambahkan.');
    }
}
