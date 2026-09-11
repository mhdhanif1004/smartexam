<?php

namespace App\Services;

use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Violation;
use App\Models\WaliKelasNote;
use Illuminate\Support\Collection;

/**
 * Service untuk semua data visualisasi Wali Kelas.
 *
 * Method-method ini tadinya private di DashboardController. Dipindah ke
 * sini supaya bisa dipakai oleh SEMUA controller Wali Kelas (dashboard,
 * nilai-akademik, pelanggaran, catatan) tanpa duplikasi.
 *
 * Pattern ini konsisten dengan FinalScoreCalculator dan ExamSummaryService.
 */
class WaliKelasDataService
{
    /**
     * Data nilai akademik untuk seluruh siswa di kelas, untuk SATU semester.
     *
     * Desain tabel grades: SATU Nilai Akhir per siswa-mapel-semester. Filter
     * `semester_id` WAJIB — tanpa ini, baris semester lain ikut tercampur.
     * Semester di-resolve controller (via ResolvesSelectedSemester), service
     * tetap testable/reusable tanpa session.
     *
     * @return array<int, array{student: Student, grades: array<int, array{subject: Subject, score: float|null, source: string, note: string|null}>}>
     */
    public function academicGrades(int $classroomId, int $semesterId): array
    {
        if ($semesterId <= 0) {
            return [];
        }

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
            ->where('semester_id', $semesterId)
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

    /**
     * Rata-rata Nilai Akhir dari seluruh siswa yang punya nilai.
     */
    public function averageGrade(array $academicGrades): ?float
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
     * Siswa yang perlu perhatian (avg <70, pelanggaran ≥3, atau belum ada nilai).
     *
     * @return array<int, array{student: Student, reason: string}>
     */
    public function siswaPerluPerhatian(array $academicGrades, int $classroomId): array
    {
        $allStudents = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        if ($allStudents->isEmpty()) {
            return [];
        }

        $studentIds = $allStudents->pluck('id');

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

            $studentGradeData = $academicGrades[$student->id] ?? null;
            if ($studentGradeData === null) {
                $reasons[] = 'Belum ada nilai akademik';
            } else {
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
     * Jumlah pelanggaran total di kelas.
     */
    public function totalViolations(int $classroomId): int
    {
        return Violation::query()
            ->whereHas('examSession', function ($query) use ($classroomId) {
                $query->whereHas('student', fn ($q) => $q->where('classroom_id', $classroomId));
            })
            ->count();
    }

    /**
     * Pelanggaran per siswa di kelas.
     *
     * @return Collection<int, array{student: Student, total: int, types: array, handled: int, unhandled: int}>
     */
    public function violationsByStudent(int $classroomId)
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
     * Baris RAW pelanggaran di kelas (untuk export).
     *
     * Satu baris = satu kejadian pelanggaran, lengkap dengan siswa + status
     * penanganan. Berbeda dari violationsByStudent() yang agregat per siswa.
     *
     * @return Collection<int, Violation>
     */
    public function violationsDetails(int $classroomId): Collection
    {
        return Violation::query()
            ->with(['examSession.student.user'])
            ->whereHas('examSession.student', fn ($q) => $q->where('classroom_id', $classroomId))
            ->orderByDesc('occurred_at')
            ->get();
    }

    /**
     * Data catatan wali kelas untuk kelas + semester tertentu.
     *
     * @return array{students: Collection, selectedStudentId: int|null, notes: Collection}
     */
    public function catatan(int $classroomId, int $semesterId): array
    {
        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $classroomId)
            ->orderBy('nisn')
            ->get();

        $selectedStudentId = request()->integer('student_id');

        $notes = WaliKelasNote::query()
            ->with(['student.user', 'waliKelas.user'])
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->when($selectedStudentId, fn ($q) => $q->where('student_id', $selectedStudentId))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id');

        return [
            'students' => $students,
            'selectedStudentId' => $selectedStudentId,
            'notes' => $notes,
        ];
    }

    /**
     * Data nilai sikap untuk kelas + semester.
     *
     * @return array{wali: WaliKelas, students: Collection, aspects: Collection, selectedSemesterId: int, existingGrades: Collection, gradesByStudent: array}
     */
    public function attitudeGrades(int $classroomId, int $selectedSemesterId, \App\Models\WaliKelas $wali): array
    {
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
            'selectedSemesterId' => $selectedSemesterId,
            'existingGrades' => $existingGrades,
            'gradesByStudent' => $gradesByStudent,
        ];
    }

    /**
     * Data distribusi rentang nilai untuk chart (bar chart).
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function gradeDistributionChart(array $academicGrades): array
    {
        $buckets = ['< 50' => 0, '50–69' => 0, '70–84' => 0, '85–100' => 0];

        foreach ($academicGrades as $studentData) {
            foreach ($studentData['grades'] as $gradeData) {
                $score = $gradeData['score'];
                if ($score === null) {
                    continue;
                }

                if ($score < 50) {
                    $buckets['< 50']++;
                } elseif ($score < 70) {
                    $buckets['50–69']++;
                } elseif ($score < 85) {
                    $buckets['70–84']++;
                } else {
                    $buckets['85–100']++;
                }
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
        ];
    }
}
