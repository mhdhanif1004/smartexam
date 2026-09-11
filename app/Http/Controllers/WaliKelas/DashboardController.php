<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\WaliKelasDataService;
use App\Traits\ResolvesSelectedSemester;
use App\Traits\ScopesWaliKelas;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    use ResolvesSelectedSemester;
    use ScopesWaliKelas;

    public function __invoke(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');
        $data = app(WaliKelasDataService::class);

        // Daftar semester + semester terpilih (session global) untuk selector.
        $selectorData = $this->semesterSelectorData();
        $selectedSemesterId = $selectorData['selectedSemesterId'];

        // Isolasi ketat: siswa HANYA dari kelas profil wali (classroom_id
        // diambil dari profil, bukan dari parameter request).
        $studentCount = Student::query()
            ->where('classroom_id', $wali->classroom_id)
            ->count();

        $academicGrades = $data->academicGrades($wali->classroom_id, $selectedSemesterId);
        $averageGrade = $data->averageGrade($academicGrades);
        $totalViolations = $data->totalViolations($wali->classroom_id);
        $siswaPerluPerhatian = $data->siswaPerluPerhatian($academicGrades, $wali->classroom_id);
        $chartDistribution = $data->gradeDistributionChart($academicGrades);

        return view('wali_kelas.dashboard', compact(
            'wali', 'studentCount', 'academicGrades', 'averageGrade',
            'totalViolations', 'siswaPerluPerhatian', 'chartDistribution',
            'selectedSemesterId',
        ) + ['semesters' => $selectorData['semesters']]);
    }
}
