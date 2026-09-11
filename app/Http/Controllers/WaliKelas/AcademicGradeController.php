<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Services\WaliKelasDataService;
use App\Traits\ResolvesSelectedSemester;
use App\Traits\ScopesWaliKelas;
use Illuminate\Contracts\View\View;

/**
 * Nilai Akademik (read-only) — siswa di kelas wali.
 *
 * Academic grades TIDAK semester-filtered (perilaku pre-existing:
 * semua nilai kelas muncul tanpa filter semester).
 */
class AcademicGradeController extends Controller
{
    use ResolvesSelectedSemester;
    use ScopesWaliKelas;

    public function index(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');
        $data = app(WaliKelasDataService::class);

        $selectorData = $this->semesterSelectorData();

        $academicGrades = $data->academicGrades($wali->classroom_id, $selectorData['selectedSemesterId']);

        return view('wali_kelas.academic-grades.index', compact(
            'wali', 'academicGrades',
        ) + ['semesters' => $selectorData['semesters'], 'selectedSemesterId' => $selectorData['selectedSemesterId']]);
    }
}
