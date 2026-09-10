<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliKelas\StoreAttitudeGradeRequest;
use App\Http\Requests\WaliKelas\StoreAttitudeGradesBulkRequest;
use App\Http\Requests\WaliKelas\UpdateAttitudeGradeRequest;
use App\Models\AttitudeGrade;
use App\Traits\ScopesWaliKelas;
use Illuminate\Http\RedirectResponse;

class AttitudeGradeController extends Controller
{
    use ScopesWaliKelas;

    /**
     * Bulk store/update nilai sikap untuk satu siswa (semua aspek sekaligus).
     *
     * Logika skor kosong (eksplisit, 3 skenario):
     * - Field skor kosong DAN belum ada record sebelumnya → SKIP, jangan create.
     * - Field skor kosong DAN record sebelumnya SUDAH ADA → DELETE record tersebut.
     * - Field skor terisi → updateOrCreate seperti biasa.
     */
    public function bulkStore(StoreAttitudeGradesBulkRequest $request): RedirectResponse
    {
        $wali = $this->currentWaliKelas();

        $studentId = (int) $request->input('student_id');
        $semesterId = (int) $request->input('semester_id');
        $grades = $request->input('grades', []);

        // Loop untuk setiap aspek
        foreach ($grades as $gradeData) {
            $aspectId = (int) $gradeData['aspect_id'];
            $score = $gradeData['score'] ?? null;
            $note = $gradeData['note'] ?? null;

            // Cek apakah record untuk aspek ini sudah ada di database
            $existingGrade = AttitudeGrade::query()
                ->where('student_id', $studentId)
                ->where('classroom_id', $wali->classroom_id)
                ->where('attitude_aspect_id', $aspectId)
                ->where('semester_id', $semesterId)
                ->first();

            // LOGIKA 3 SKENARIO:
            // 1. Skor kosong DAN belum ada record → SKIP (tidak create)
            // 2. Skor kosong DAN sudah ada record → DELETE (hapus record)
            // 3. Skor terisi → updateOrCreate

            if ($score === null || $score === '') {
                // Skor kosong
                if ($existingGrade !== null) {
                    // Skenario 2: hapus record yang ada
                    $existingGrade->delete();
                }
                // Skenario 1: skip (tidak create record baru)
            } else {
                // Skor terisi
                AttitudeGrade::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'classroom_id' => $wali->classroom_id,
                        'attitude_aspect_id' => $aspectId,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'wali_kelas_id' => $wali->id,
                        'score' => $score,
                        'note' => $note,
                    ]
                );
            }
        }

        return back()->with('success', 'Nilai sikap berhasil diperbarui.');
    }

    /**
     * Simpan nilai sikap baru (satu siswa, satu aspek, satu semester).
     */
    public function store(StoreAttitudeGradeRequest $request): RedirectResponse
    {
        $wali = $this->currentWaliKelas();

        AttitudeGrade::updateOrCreate(
            [
                'student_id' => (int) $request->input('student_id'),
                'classroom_id' => $wali->classroom_id,
                'attitude_aspect_id' => (int) $request->input('attitude_aspect_id'),
                'semester_id' => (int) $request->input('semester_id'),
            ],
            [
                'wali_kelas_id' => $wali->id,
                'score' => $request->input('score'),
                'note' => $request->input('note'),
            ]
        );

        return back()->with('success', 'Nilai sikap berhasil disimpan.');
    }

    /**
     * Update nilai sikap yang sudah ada.
     */
    public function update(UpdateAttitudeGradeRequest $request, AttitudeGrade $attitudeGrade): RedirectResponse
    {
        $wali = $this->currentWaliKelas();

        abort_unless($attitudeGrade->classroom_id === $wali->classroom_id, 403, 'Anda tidak memiliki akses ke data ini.');

        $attitudeGrade->update([
            'student_id' => (int) $request->input('student_id'),
            'attitude_aspect_id' => (int) $request->input('attitude_aspect_id'),
            'semester_id' => (int) $request->input('semester_id'),
            'wali_kelas_id' => $wali->id,
            'score' => $request->input('score'),
            'note' => $request->input('note'),
        ]);

        return back()->with('success', 'Nilai sikap berhasil diperbarui.');
    }

    /**
     * Hapus nilai sikap.
     */
    public function destroy(AttitudeGrade $attitudeGrade): RedirectResponse
    {
        $wali = $this->currentWaliKelas();

        abort_unless($attitudeGrade->classroom_id === $wali->classroom_id, 403, 'Anda tidak memiliki akses ke data ini.');

        $attitudeGrade->delete();

        return back()->with('success', 'Nilai sikap berhasil dihapus.');
    }
}
