<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaliKelas\StoreAttitudeGradeRequest;
use App\Http\Requests\WaliKelas\UpdateAttitudeGradeRequest;
use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\Semester;
use App\Models\Student;
use App\Traits\ScopesWaliKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttitudeGradeController extends Controller
{
    use ScopesWaliKelas;

    /**
     * Tampilkan daftar nilai sikap untuk kelas wali, difilter per semester.
     */
    public function index(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');

        $semesters = Semester::query()->orderByDesc('year')->orderByDesc('semester')->get();
        $activeSemester = Semester::where('is_active', true)->first();
        $selectedSemesterId = request()->integer('semester_id', $activeSemester?->id ?? $semesters->first()?->id);

        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $wali->classroom_id)
            ->orderBy('nisn')
            ->get();

        $aspects = AttitudeAspect::query()->orderBy('name')->get();

        $existingGrades = AttitudeGrade::query()
            ->where('classroom_id', $wali->classroom_id)
            ->where('semester_id', $selectedSemesterId)
            ->get()
            ->keyBy(fn (AttitudeGrade $g) => $g->student_id.'_'.$g->attitude_aspect_id);

        return view('wali_kelas.attitude-grades.index', compact(
            'wali', 'students', 'aspects', 'semesters', 'selectedSemesterId', 'existingGrades',
        ));
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
