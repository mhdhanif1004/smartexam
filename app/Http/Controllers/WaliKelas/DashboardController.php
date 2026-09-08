<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Traits\ScopesWaliKelas;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesWaliKelas;

    public function __invoke(): View
    {
        $wali = $this->currentWaliKelas()->load('classroom');

        // Isolasi ketat: siswa HANYA dari kelas profil wali (classroom_id
        // diambil dari profil, bukan dari parameter request).
        $studentCount = Student::query()
            ->where('classroom_id', $wali->classroom_id)
            ->count();

        // ========== NILAI AKADEMIK (Read-Only, Override-Aware) ==========
        // Mengikuti pola GradeController Guru Mapel:
        // 1. Ambil semua grades dengan classroom_id = kelas wali
        // 2. Untuk setiap siswa+mapel+grade_type: is_override=true → grades.score,
        //    jika tidak ada/false → exam_results.total_score (via ExamSession→ExamSchedule→subject)
        $academicGrades = $this->getAcademicGrades($wali->classroom_id);

        return view('wali_kelas.dashboard', compact('wali', 'studentCount', 'academicGrades'));
    }

    /**
     * Susun data nilai akademik untuk kelas wali.
     *
     * Mengembalikan collection terstruktur:
     * [
     *   student_id => [
     *     'student' => Student,
     *     'grades' => [
     *       subject_id => [
     *         'subject' => Subject,
     *         'score' => float|null,
     *         'source' => 'override'|'auto'|'none',
     *         'note' => string|null,
     *       ],
     *     ],
     *   ],
     * ]
     */
    private function getAcademicGrades(int $classroomId): array
    {
        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        if ($students->isEmpty()) {
            return [];
        }

        $studentIds = $students->pluck('id');

        // Grades manual / override milik guru untuk kelas ini
        $grades = Grade::query()
            ->with(['subject'])
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        // ExamResult CBT terakhir per siswa per mapel
        $latestResultsByStudentSubject = ExamSession::query()
            ->with(['examResult', 'examSchedule.subject'])
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_TIMED_OUT])
            ->whereHas('examResult')
            ->whereHas('examSchedule')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(function ($sessions) {
                return $sessions->groupBy(fn ($s) => $s->examSchedule->subject_id)->map->first();
            });

        // Subject list
        $subjectIds = $grades->flatMap(fn ($g) => $g->pluck('subject_id'))
            ->merge($latestResultsByStudentSubject->flatMap(fn ($bySubject) => $bySubject->keys()))
            ->unique()
            ->values();

        $subjects = Subject::query()
            ->whereIn('id', $subjectIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($students as $student) {
            $studentGrades = $grades->get($student->id, collect())->keyBy('subject_id');
            $studentResults = $latestResultsByStudentSubject->get($student->id, collect());

            $bySubject = [];
            foreach ($subjectIds as $subjectId) {
                $subject = $subjects->get($subjectId);
                if ($subject === null) {
                    continue;
                }

                $grade = $studentGrades->get($subjectId);
                $resultSession = $studentResults->get($subjectId);
                $examResult = $resultSession?->examResult;

                if ($grade !== null && $grade->is_override) {
                    $bySubject[$subjectId] = [
                        'subject' => $subject,
                        'score' => $grade->score !== null ? (float) $grade->score : null,
                        'source' => 'override',
                        'note' => $grade->note,
                    ];
                } elseif ($grade !== null && ! $grade->is_override) {
                    // Source-of-truth untuk is_override=false adalah exam_results
                    // (mengikuti konvensi GuruMapel\GradeController:86-88). Fallback
                    // ke grades.score hanya jika exam_result belum ada.
                    $autoScore = $examResult !== null
                        ? ($examResult->total_score !== null ? (float) $examResult->total_score : null)
                        : ($grade->score !== null ? (float) $grade->score : null);

                    $bySubject[$subjectId] = [
                        'subject' => $subject,
                        'score' => $autoScore,
                        'source' => 'auto',
                        'note' => $grade->note,
                    ];
                } elseif ($examResult !== null) {
                    $bySubject[$subjectId] = [
                        'subject' => $subject,
                        'score' => $examResult->total_score !== null ? (float) $examResult->total_score : null,
                        'source' => 'auto',
                        'note' => null,
                    ];
                }
            }

            if (! empty($bySubject)) {
                $result[$student->id] = [
                    'student' => $student,
                    'grades' => $bySubject,
                ];
            }
        }

        return $result;
    }
}
