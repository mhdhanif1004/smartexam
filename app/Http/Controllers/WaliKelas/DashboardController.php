<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Violation;
use App\Models\WaliKelasNote;
use App\Traits\ScopesWaliKelas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

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

        $academicGrades = $this->getAcademicGrades($wali->classroom_id);

        $attitudeGrades = $this->getAttitudeGrades($wali->classroom_id);

        // Stat cards
        $totalViolations = $this->getTotalViolations($wali->classroom_id);
        $averageGrade = $this->computeAverageGrade($academicGrades);
        $siswaPerluPerhatian = $this->getSiswaPerluPerhatian($academicGrades, $wali->classroom_id);
        $violationsByStudent = $this->getViolationsByStudent($wali->classroom_id);

        $catatan = $this->getCatatan($wali->classroom_id);

        return view('wali_kelas.dashboard', compact(
            'wali', 'studentCount', 'academicGrades', 'attitudeGrades',
            'totalViolations', 'averageGrade', 'siswaPerluPerhatian', 'violationsByStudent',
            'catatan',
        ));
    }

    /**
     * Susun data Catatan Wali Kelas untuk dashboard.
     *
     * Mengembalikan array terstruktur:
     * [
     *   'students' => Collection<Student> (siswa di kelas wali),
     *   'semesters' => Collection<Semester> (semesters untuk filter),
     *   'selectedSemesterId' => int (semester yang dipilih saat ini),
     *   'selectedStudentId' => int|null (siswa yang difilter),
     *   'notes' => Collection keyed by student_id: Collection<WaliKelasNote>
     *      per siswa (hasil groupBy('student_id'), terurut created_at DESC),
     * ]
     */
    private function getCatatan(int $classroomId): array
    {
        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        $semesters = Semester::query()->orderByDesc('year')->orderByDesc('semester')->get();
        $activeSemester = Semester::where('is_active', true)->first();
        $selectedSemesterId = request()->integer(
            'semester_id',
            $activeSemester?->id ?? $semesters->first()?->id
        );

        $selectedStudentId = request()->integer('student_id');

        // Satu query + groupBy, mengikuti pola query asli catatan index.
        $notes = WaliKelasNote::query()
            ->with(['student.user', 'waliKelas.user'])
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $selectedSemesterId)
            ->when($selectedStudentId, fn ($q) => $q->where('student_id', $selectedStudentId))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id');

        return [
            'students' => $students,
            'semesters' => $semesters,
            'selectedSemesterId' => $selectedSemesterId,
            'selectedStudentId' => $selectedStudentId,
            'notes' => $notes,
        ];
    }

    /**
     * Hitung total pelanggaran untuk seluruh siswa di kelas tertentu.
     *
     * Dipakai untuk stat card dan Rekap Pelanggaran (poin 3 mendatang).
     */
    private function getTotalViolations(int $classroomId): int
    {
        return Violation::query()
            ->whereHas('examSession', function ($query) use ($classroomId) {
                $query->whereHas('student', fn ($q) => $q->where('classroom_id', $classroomId));
            })
            ->count();
    }

    /**
     * Susun data pelanggaran per siswa di kelas tertentu.
     *
     * Mengembalikan Collection keyed by student_id, masing-masing berisi:
     * [
     *   'student' => Student,
     *   'total' => int,
     *   'types' => ['berpindah_tab' => 2, ...],
     *   'handled' => int,
     *   'unhandled' => int,
     * ]
     *
     * @return Collection<int, array>
     */
    private function getViolationsByStudent(int $classroomId)
    {
        $allViolations = Violation::query()
            ->with('examSession.student')
            ->whereHas('examSession.student', fn ($q) => $q->where('classroom_id', $classroomId))
            ->get();

        $allStudents = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        $grouped = $allViolations->groupBy(fn (Violation $v) => $v->examSession?->student_id);

        return $allStudents->mapWithKeys(function (Student $student) use ($grouped) {
            $violations = $grouped->get($student->id, collect());
            $types = $violations->pluck('violation_type')->countBy()->toArray();
            $handled = $violations->where('handled_by_supervisor', true)->count();

            return [$student->id => [
                'student' => $student,
                'total' => $violations->count(),
                'types' => $types,
                'handled' => $handled,
                'unhandled' => $violations->count() - $handled,
            ]];
        });
    }

    /**
     * Rata-rata Nilai Akhir dari seluruh siswa yang punya nilai.
     */
    private function computeAverageGrade(array $academicGrades): ?float
    {
        $scores = [];

        foreach ($academicGrades as $studentData) {
            foreach ($studentData['grades'] as $gradeData) {
                if ($gradeData['score'] !== null) {
                    $scores[] = $gradeData['score'];
                }
            }
        }

        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Identifikasi siswa perlu perhatian:
     * - Rata-rata Nilai Akhir < 70, ATAU
     * - Total pelanggaran >= 3, ATAU
     * - Belum ada nilai sama sekali
     *
     * @return array<int, array{student: Student, reason: string}>
     */
    private function getSiswaPerluPerhatian(array $academicGrades, int $classroomId): array
    {
        // Semua siswa di kelas
        $allStudents = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        if ($allStudents->isEmpty()) {
            return [];
        }

        $studentIds = $allStudents->pluck('id');

        // Hitung pelanggaran per siswa
        $violationCounts = Violation::query()
            ->whereHas('examSession', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->whereIn('exam_session_id', fn ($q) => $q
                ->select('exam_sessions.id')
                ->from('exam_sessions')
                ->whereIn('student_id', $studentIds)
            )
            ->selectRaw('exam_session_id, COUNT(*) as violation_count')
            ->groupBy('exam_session_id')
            ->pluck('violation_count', 'exam_session_id')
            ->toArray();

        // Map violation counts ke student_id
        $violationsByStudent = [];
        $sessions = ExamSession::query()
            ->whereIn('student_id', $studentIds)
            ->select('id', 'student_id')
            ->get();
        foreach ($sessions as $session) {
            $count = $violationCounts[$session->id] ?? 0;
            if ($count > 0) {
                $violationsByStudent[$session->student_id] = ($violationsByStudent[$session->student_id] ?? 0) + $count;
            }
        }

        $result = [];

        foreach ($allStudents as $student) {
            $reasons = [];

            // Cek 1: Belum ada nilai
            $studentGradeData = $academicGrades[$student->id] ?? null;
            if ($studentGradeData === null) {
                $reasons[] = 'Belum ada nilai akademik';
            } else {
                // Cek 2: Rata-rata Nilai Akhir < 70
                $scores = [];
                foreach ($studentGradeData['grades'] as $gradeData) {
                    if ($gradeData['score'] !== null) {
                        $scores[] = $gradeData['score'];
                    }
                }
                if (! empty($scores)) {
                    $avg = array_sum($scores) / count($scores);
                    if ($avg < 70) {
                        $reasons[] = 'Rata-rata nilai '.number_format($avg, 1);
                    }
                }
            }

            // Cek 3: Pelanggaran >= 3
            $violationCount = $violationsByStudent[$student->id] ?? 0;
            if ($violationCount >= 3) {
                $reasons[] = $violationCount.' pelanggaran';
            }

            if (! empty($reasons)) {
                $result[$student->id] = [
                    'student' => $student,
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        return $result;
    }

    /**
     * Susun data nilai sikap untuk kelas wali (untuk dashboard tab).
     *
     * Mengembalikan array terstruktur:
     * [
     *   'wali' => WaliKelas,
     *   'students' => Collection<Student>,
     *   'aspects' => Collection<AttitudeAspect>,
     *   'semesters' => Collection<Semester>,
     *   'selectedSemesterId' => int,
     *   'existingGrades' => Collection<AttitudeGrade> (keyed by "$student_id.$aspect_id"),
     *   'gradesByStudent' => array<int, array<int, array{score:float|null,note:string|null}>>
     *      keyed by student_id then aspect_id, untuk prefill modal.
     * ]
     */
    private function getAttitudeGrades(int $classroomId): array
    {
        $wali = $this->currentWaliKelas();

        $semesters = Semester::query()->orderByDesc('year')->orderByDesc('semester')->get();
        $activeSemester = Semester::where('is_active', true)->first();
        $selectedSemesterId = request()->integer('semester_id', $activeSemester?->id ?? $semesters->first()?->id);

        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        $aspects = AttitudeAspect::query()->orderBy('name')->get();

        $existingGrades = AttitudeGrade::query()
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $selectedSemesterId)
            ->get()
            ->keyBy(fn (AttitudeGrade $g) => $g->student_id.'_'.$g->attitude_aspect_id);

        // Struktur per-siswa per-aspek untuk prefill modal edit.
        $gradesByStudent = [];
        foreach ($students as $student) {
            foreach ($aspects as $aspect) {
                $key = $student->id.'_'.$aspect->id;
                $grade = $existingGrades->get($key);
                $gradesByStudent[$student->id][$aspect->id] = [
                    'score' => $grade?->score !== null ? (float) $grade->score : null,
                    'note' => $grade?->note ?? '',
                ];
            }
        }

        return [
            'wali' => $wali,
            'students' => $students,
            'aspects' => $aspects,
            'semesters' => $semesters,
            'selectedSemesterId' => $selectedSemesterId,
            'existingGrades' => $existingGrades,
            'gradesByStudent' => $gradesByStudent,
        ];
    }

    /**
     * Susun data nilai akademik untuk kelas wali.
     *
     * Grades adalah SATU-SATUNYA sumber Nilai Akhir (pasca-redesign):
     * - is_override=true  → koreksi manual guru (menang)
     * - is_override=false → Nilai Akhir dari FinalScoreCalculator yang
     *   sudah disinkronkan oleh GuruMapel\GradeController::syncFinalScores.
     * Tidak lagi membaca exam_results langsung; itu sekarang menjadi bahan
     * baku subject_grades yang diagregasi lewat kalkulator.
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

        $grades = Grade::query()
            ->with(['subject'])
            ->where('classroom_id', $classroomId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        $subjectIds = $grades->flatMap(fn ($g) => $g->pluck('subject_id'))
            ->unique()
            ->values();

        $subjects = Subject::query()
            ->whereIn('id', $subjectIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($students as $student) {
            $studentGrades = $grades->get($student->id, collect())->keyBy('subject_id');

            $bySubject = [];
            foreach ($subjectIds as $subjectId) {
                $subject = $subjects->get($subjectId);
                $grade = $studentGrades->get($subjectId);

                if ($subject === null || $grade === null) {
                    continue;
                }

                $bySubject[$subjectId] = [
                    'subject' => $subject,
                    'score' => $grade->score !== null ? (float) $grade->score : null,
                    'source' => $grade->is_override ? 'override' : 'auto',
                    'note' => $grade->note,
                ];
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
