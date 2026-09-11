<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Services\WaliKelasDataService;
use App\Traits\ResolvesSelectedSemester;
use App\Traits\ScopesWaliKelas;
use Illuminate\Contracts\View\View;

/**
 * Rekap Pelanggaran — seluruh siswa di kelas wali.
 *
 * Pelanggaran TIDAK semester-filtered (perilaku pre-existing: seluruh
 * pelanggaran sesi ujian kelas muncul).
 */
class ViolationController extends Controller
{
    use ResolvesSelectedSemester;
    use ScopesWaliKelas;

    public function index(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');
        $data = app(WaliKelasDataService::class);

        $selectorData = $this->semesterSelectorData();

        $totalViolations = $data->totalViolations($wali->classroom_id);
        $violationsByStudent = $data->violationsByStudent($wali->classroom_id);

        return view('wali_kelas.violations.index', compact(
            'wali', 'totalViolations', 'violationsByStudent',
        ) + ['semesters' => $selectorData['semesters'], 'selectedSemesterId' => $selectorData['selectedSemesterId']]);
    }
}
