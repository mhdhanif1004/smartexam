<?php

namespace App\Http\Controllers\GuruMapel;

use App\Exports\GradesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuruMapel\StoreGuruMapelEntriesRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAttendance;
use App\Models\SubjectGrade;
use App\Services\FinalScoreCalculator;
use App\Traits\ScopesGuruMapel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeController extends Controller
{
    use ScopesGuruMapel;

    public function index(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;

        $semesters = Semester::query()
            ->with('academicYear')
            ->orderByDesc(
                AcademicYear::query()
                    ->select('nama')
                    ->whereColumn('academic_years.id', 'semesters.academic_year_id')
            )
            ->orderByDesc('jenis')
            ->get();
        $activeSemesterId = Semester::getActive()?->id;
        $semesterId = $request->filled('semester_id') ? (int) $request->integer('semester_id') : $activeSemesterId;

        $examTypes = ExamType::query()->orderBy('sort_order')->get();

        $students = collect();
        $rows = collect();
        $classroom = null;
        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection && $semesterId !== null) {
            $students = Student::query()
                ->with('user')
                ->where('classroom_id', $classroomId)
                ->orderBy('nisn')
                ->get();

            $classroom = Classroom::query()->find($classroomId);

            $harianTypeId = ExamType::query()->where('code', 'harian')->value('id');

            // Sinkronkan Nilai Akhir ke tabel grades dulu (non-override),
            // lalu load grades final sekali untuk semua siswa (hindari N+1).
            $this->syncFinalScores($guru, $subjectId, $classroomId, $semesterId, $students);

            $existingGrades = Grade::query()
                ->where('guru_mapel_id', $guru->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('semester_id', $semesterId)
                ->get()
                ->keyBy('student_id');

            // Semua baris subject_grades siswa utk mapel-kelas-semester ini.
            $subjectGrades = SubjectGrade::query()
                ->with('examType')
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('semester_id', $semesterId)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('student_id');

            $attendanceByStudent = SubjectAttendance::query()
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('semester_id', $semesterId)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');

            $calculator = app(FinalScoreCalculator::class);

            $rows = $students->map(function (Student $student) use ($subjectId, $semesterId, $subjectGrades, $attendanceByStudent, $calculator, $existingGrades, $harianTypeId) {
                $studentGrades = $subjectGrades->get($student->id, collect());
                $attendance = $attendanceByStudent->get($student->id);

                $breakdown = $this->breakdownByType($studentGrades, $attendance);

                $calc = $calculator->calculate($student->id, $subjectId, $semesterId);

                $existing = $existingGrades->get($student->id);

                $effectiveScore = $existing !== null && $existing->is_override
                    ? (float) $existing->score
                    : $calc['final_score'];

                return [
                    'student' => $student,
                    'breakdown' => $breakdown,
                    'average' => $calc['final_score'],
                    'score' => $effectiveScore !== null ? round($effectiveScore, 2) : null,
                    'source' => $existing !== null && $existing->is_override ? 'override' : ($calc['final_score'] !== null ? 'auto' : 'none'),
                    'note' => $existing?->note,
                    'harianCount' => $harianTypeId !== null ? $studentGrades->where('exam_type_id', $harianTypeId)->count() : 0,
                    'attendanceDays' => $attendance !== null ? [
                        'total_days' => $attendance->total_days,
                        'present_days' => $attendance->present_days,
                        'absent_days' => $attendance->absent_days,
                    ] : null,
                ];
            })->values();
        }

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $guruId = $guru->id;
        $rowsByStudent = $rows->keyBy(fn ($row) => $row['student']->id);

        return view('guru_mapel.grades.index', compact(
            'guru', 'guruId', 'subjects', 'classrooms', 'subjectId', 'classroomId',
            'students', 'validSelection', 'classroom', 'rows', 'semesters', 'semesterId',
            'activeSemesterId', 'examTypes', 'rowsByStudent',
        ));
    }

    public function store(StoreGuruMapelEntriesRequest $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->input('subject_id');
        $classroomId = (int) $request->input('classroom_id');
        $semesterId = (int) $request->input('semester_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        DB::transaction(function () use ($request, $guru, $subjectId, $classroomId, $semesterId) {
            // Sinkronkan subject_attendances dari input attendance[].
            $attendanceInputs = $request->input('attendance', []);
            foreach ($attendanceInputs as $studentId => $att) {
                $total = (int) ($att['total_days'] ?? 0);
                $present = (int) ($att['present_days'] ?? 0);
                $absent = (int) ($att['absent_days'] ?? 0);

                if ($total === 0 && $present === 0 && $absent === 0) {
                    SubjectAttendance::query()
                        ->where('student_id', (int) $studentId)
                        ->where('classroom_id', $classroomId)
                        ->where('subject_id', $subjectId)
                        ->where('semester_id', $semesterId)
                        ->delete();

                    continue;
                }

                $attendance = SubjectAttendance::updateOrCreate(
                    [
                        'student_id' => (int) $studentId,
                        'classroom_id' => $classroomId,
                        'subject_id' => $subjectId,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'guru_mapel_id' => $guru->id,
                        'total_days' => $total,
                        'present_days' => $present,
                        'absent_days' => $absent,
                    ]
                );

                if ($total > 0) {
                    $attendance->materialize();
                }
            }

            // Sinkronkan subject_grades dari input entries[student_id][type].
            $entries = $request->input('entries', []);
            foreach ($entries as $studentId => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (! empty($entry['harian']['score']) || ! empty($entry['harian']['title'])) {
                    $this->upsertEntry(
                        $guru->id, (int) $studentId, $classroomId, $subjectId, $semesterId,
                        'harian', $entry['harian']
                    );
                }

                foreach (['uts', 'uas'] as $typeCode) {
                    if (! empty($entry[$typeCode]['score'])) {
                        $this->upsertEntry(
                            $guru->id, (int) $studentId, $classroomId, $subjectId, $semesterId,
                            $typeCode, $entry[$typeCode]
                        );
                    }
                }
            }

            // Recalc Nilai Akhir semua siswa kelas ini ke tabel grades.
            $students = Student::query()->where('classroom_id', $classroomId)->get();
            $this->syncFinalScores($guru, $subjectId, $classroomId, $semesterId, $students);
        });

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    /**
     * Upsert satu baris subject_grades (manual) utk satu siswa.
     */
    private function upsertEntry(
        int $guruId,
        int $studentId,
        int $classroomId,
        int $subjectId,
        int $semesterId,
        string $typeCode,
        array $data
    ): void {
        $type = ExamType::query()->where('code', $typeCode)->firstOrFail();

        $title = $typeCode === 'harian'
            ? trim((string) ($data['title'] ?? ''))
            : strtoupper($typeCode);

        if ($title === '') {
            // Auto-suggest "UH n" dari jumlah entri harian yang sudah ada.
            $count = SubjectGrade::query()
                ->where('student_id', $studentId)
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $subjectId)
                ->where('semester_id', $semesterId)
                ->where('exam_type_id', $type->id)
                ->count();

            $title = 'UH '.($count + 1);
        }

        $score = (float) $data['score'];

        SubjectGrade::updateOrCreate(
            [
                'student_id' => $studentId,
                'classroom_id' => $classroomId,
                'subject_id' => $subjectId,
                'semester_id' => $semesterId,
                'exam_type_id' => $type->id,
                'title' => $title,
            ],
            [
                'guru_mapel_id' => $guruId,
                'score' => $score,
                'note' => $data['note'] ?? null,
                'source' => SubjectGrade::SOURCE_MANUAL,
                'is_override' => true,
                'taken_at' => now()->toDateString(),
            ]
        );
    }

    private function typeId(string $code): ?int
    {
        return ExamType::query()->where('code', $code)->value('id');
    }

    /**
     * Susun breakdown per kategori untuk satu siswa. Key memakai kode jenis
     * ujian ('harian', 'uts', 'uas', 'kehadiran') agar konsisten dipakai view
     * dan export.
     */
    private function breakdownByType(Collection $studentGrades, ?SubjectAttendance $attendance): array
    {
        $result = [];
        $byType = $studentGrades->groupBy(
            fn (SubjectGrade $g) => $g->examType?->code ?? $g->exam_type_id
        );

        foreach ($byType as $code => $group) {
            $scores = $group->map(fn (SubjectGrade $g) => (float) $g->score)->values();

            $result[$code] = [
                'type' => $group->first()->examType?->name ?? 'Kategori',
                'entries' => $group->map(function (SubjectGrade $g) {
                    return [
                        'title' => $g->title,
                        'score' => (float) $g->score,
                        'note' => $g->note,
                    ];
                })->values()->all(),
                'average' => round($scores->avg(), 2),
            ];
        }

        if ($attendance !== null && $attendance->score() !== null) {
            $result['kehadiran'] = [
                'type' => 'Kehadiran',
                'entries' => [
                    [
                        'title' => SubjectAttendance::ATTENDANCE_TITLE,
                        'score' => (float) $attendance->score(),
                        'note' => $attendance->note,
                    ],
                ],
                'average' => (float) $attendance->score(),
            ];
        }

        return $result;
    }

    /**
     * Hitung Nilai Akhir tiap siswa dgn FinalScoreCalculator lalu sinkronkan
     * ke tabel grades. Baris override milik guru tidak pernah tertimpa.
     */
    private function syncFinalScores(
        $guru,
        int $subjectId,
        int $classroomId,
        int $semesterId,
        Collection $students
    ): void {
        $calculator = app(FinalScoreCalculator::class);

        // Preload grades existing utk mapel-kelas-SEMESTER ini (1 query, bukan N+1).
        $existingGrades = Grade::query()
            ->where('guru_mapel_id', $guru->id)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy('student_id');

        foreach ($students as $student) {
            $existing = $existingGrades->get($student->id);
            $final = $calculator->calculate($student->id, $subjectId, $semesterId)['final_score'];

            if ($existing !== null && $existing->is_override) {
                continue;
            }

            if ($final === null) {
                // Tidak ada data nilai → hapus baris auto agar tidak menyesatkan.
                if ($existing !== null) {
                    $existing->delete();
                }

                continue;
            }

            Grade::updateOrCreate(
                [
                    'guru_mapel_id' => $guru->id,
                    'student_id' => $student->id,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                    'semester_id' => $semesterId,
                ],
                [
                    'score' => round($final, 2),
                    'semester_id' => $semesterId,
                    'note' => $existing?->note,
                    'is_override' => false,
                ]
            );
        }
    }

    /**
     * Detail jawaban siswa: seluruh soal pada sesi ujian terakhir untuk
     * mapel-kelas yang diampu — termasuk soal yang TIDAK dijawab. Query
     * dimulai dari SEMUA soal (via question_classroom pivot), lalu LEFT
     * JOIN ke exam_answers milik siswa. Soal tak terjawab mendapat stub
     * ExamAnswer (score=0) supaya form koreksi guru tetap jalan.
     */
    public function detail(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');
        $studentId = (int) $request->integer('student_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $student = Student::query()
            ->with('user')
            ->whereKey($studentId)
            ->where('classroom_id', $classroomId)
            ->firstOrFail();

        $classroom = Classroom::query()->findOrFail($classroomId);
        $subject = Subject::query()->findOrFail($subjectId);

        // Jadwal mapel ini yang benar-benar dipakai siswa (sesi eksis);
        // tidak lagi mencocokkan class_name yang sering kosong di produksi.
        $scheduleIds = ExamSchedule::query()
            ->where('subject_id', $subjectId)
            ->whereHas('examSessions', fn ($q) => $q->where('student_id', $studentId))
            ->pluck('id');

        $session = null;
        if ($scheduleIds->isNotEmpty()) {
            $session = ExamSession::query()
                ->with(['examSchedule.subject'])
                ->where('student_id', $studentId)
                ->whereIn('exam_schedule_id', $scheduleIds)
                ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_TIMED_OUT])
                ->orderByDesc('id')
                ->first();
        }

        $activeSemesterId = Semester::getActive()?->id;

        $grade = $activeSemesterId !== null
            ? Grade::query()
                ->where('guru_mapel_id', $guru->id)
                ->where('student_id', $studentId)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('semester_id', $activeSemesterId)
                ->first()
            : null;

        // === Sumber kebenaran: SEMUA soal yang relevan untuk mapel+kelas ===
        $allQuestions = Question::query()
            ->where('subject_id', $subjectId)
            ->targetingClassroom($classroomId)
            ->orderBy('id')
            ->get();

        // Map jawaban siswa berdasarkan question_id
        $answersByQuestion = collect();
        if ($session) {
            $answersByQuestion = ExamAnswer::query()
                ->where('exam_session_id', $session->id)
                ->whereIn('question_id', $allQuestions->pluck('id'))
                ->get()
                ->keyBy('question_id');
        }

        // Untuk soal yang TIDAK dijawab: buat objek ExamAnswer IN-MEMORY
        // SAJA (bukan `::create`) supaya form koreksi guru tetap jalan tanpa
        // menulis row ke database. Stub baru hanya disimpan saat guru benar-
        // benar klik "Simpan Skor & Nilai".
        if ($session) {
            foreach ($allQuestions as $question) {
                if (! $answersByQuestion->has($question->id)) {
                    $stub = new ExamAnswer([
                        'exam_session_id' => $session->id,
                        'question_id' => $question->id,
                        'student_answer' => null,
                        'score' => 0,
                    ]);
                    $stub->setRelation('question', $question);
                    // exists tetap false → view bisa bedakan dari jawaban asli
                    $answersByQuestion->put($question->id, $stub);
                }
            }
        }

        return view('guru_mapel.grades.detail', compact(
            'guru', 'student', 'classroom', 'subject', 'session', 'grade',
            'subjectId', 'classroomId', 'studentId', 'allQuestions', 'activeSemesterId',
        ));
    }

    /**
     * Simpan koreksi skor per jawaban dari halaman detail. Dua input terpisah:
     * - `scores[answer_id]`: update skor jawaban yang SUDAH ada di DB.
     * - `new_scores[question_id]`: buat ExamAnswer BARU untuk soal yang tidak
     *   dijawab siswa — row baru hanya tercipta saat guru benar-benar mengisi
     *   koreksi dan klik Simpan (bukan saat halaman dibuka).
     * Total nilai dihitung ulang dari seluruh skor per soal sesi tersebut.
     * Nilai juga ditulis ke subject_grades (source=manual) supaya breakdown
     * per jenis ujian konsisten, lalu Nilai Akhir di-recalc ke grades.
     */
    public function saveScores(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'session_id' => ['required', 'integer'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'scores' => ['nullable', 'array'],
            'new_scores' => ['nullable', 'array'],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $classroomId = (int) $validated['classroom_id'];
        $studentId = (int) $validated['student_id'];

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $session = ExamSession::query()
            ->with('examSchedule')
            ->whereKey((int) $validated['session_id'])
            ->firstOrFail();

        [$subjectId, $classroomId] = $this->updateAnswerScores($request, $session, $studentId, $guru->id);

        // Total nilai dari seluruh skor per soal pada sesi ini.
        $session->load('examAnswers');
        $total = (float) $session->examAnswers()->sum('score');

        // Simpan koreksi guru ke tabel grades (override menang).
        $grade = Grade::updateOrCreate(
            [
                'guru_mapel_id' => $guru->id,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
            ],
            [
                'score' => round($total, 2),
                'semester_id' => $validated['semester_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_override' => true,
            ]
        );

        // Koreksi guru juga tercatat sebagai baris subject_grades manual
        // (title='Koreksi Guru') supaya breakdown per jenis ujian konsisten.
        $examTypeId = $session->examSchedule?->examPeriod?->exam_type_id
            ?? ExamType::query()->where('code', 'uas')->value('id');

        if ($examTypeId !== null && $grade->semester_id !== null) {
            SubjectGrade::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'classroom_id' => $classroomId,
                    'subject_id' => $subjectId,
                    'semester_id' => $grade->semester_id,
                    'exam_type_id' => $examTypeId,
                    'title' => 'Koreksi Guru',
                ],
                [
                    'guru_mapel_id' => $guru->id,
                    'score' => round($total, 2),
                    'note' => $validated['note'] ?? null,
                    'source' => SubjectGrade::SOURCE_MANUAL,
                    'is_override' => true,
                    'taken_at' => now()->toDateString(),
                ]
            );
        }

        return redirect()
            ->route('guru_mapel.grades.detail', [
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
                'student_id' => $studentId,
            ])
            ->with('success', 'Skor jawaban siswa berhasil disimpan.');
    }

    /**
     * Update skor exam_answers existing + buat jawaban baru untuk soal yang
     * belum dijawab (new_scores). Mengembalikan [subjectId, classroomId].
     *
     * @return array{0: int, 1: int}
     */
    private function updateAnswerScores(Request $request, ExamSession $session, int $studentId, int $guruId): array
    {
        $scores = $request->input('scores', []);
        $newScores = $request->input('new_scores', []);

        $classroomId = (int) $session->student?->classroom_id;

        if ($classroomId === 0) {
            abort(422, 'Siswa tidak memiliki kelas.');
        }

        $subjectId = (int) $session->examSchedule?->subject_id;

        if ($subjectId === 0) {
            abort(422, 'Jadwal ujian tidak memiliki mapel.');
        }

        if (! $this->currentGuru()->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        // Validasi skor per jawaban: tidak boleh melebihi bobot soal.
        foreach ($scores as $answerId => $value) {
            $answer = ExamAnswer::query()
                ->whereKey((int) $answerId)
                ->where('exam_session_id', $session->id)
                ->firstOrFail();

            $question = $answer->question;
            $maxWeight = (float) ($question?->score_weight ?? 0);

            if ($value !== null && (float) $value > $maxWeight) {
                return $this->abortValidation("scores.{$answerId}", 'Skor melebihi bobot soal.');
            }

            $answer->update([
                'score' => ($value === null || $value === '') ? null : (float) $value,
            ]);
        }

        // Buat jawaban baru utk soal yang tadinya tidak dijawab.
        foreach ($newScores as $questionId => $value) {
            $question = Question::query()
                ->whereKey((int) $questionId)
                ->where('subject_id', $subjectId)
                ->targetingClassroom($classroomId)
                ->first();

            if ($question === null) {
                return $this->abortValidation("new_scores.{$questionId}", 'Soal tidak valid untuk kelas ini.');
            }

            $maxWeight = (float) $question->score_weight;

            if ($value !== null && (float) $value > $maxWeight) {
                return $this->abortValidation("new_scores.{$questionId}", 'Skor melebihi bobot soal.');
            }

            $scoreValue = ($value === null || $value === '') ? null : (float) $value;

            // Hanya buat row jika guru benar-benar mengisi skor (bukan kosong)
            if ($scoreValue !== null) {
                ExamAnswer::create([
                    'exam_session_id' => $session->id,
                    'question_id' => (int) $questionId,
                    'student_answer' => null,
                    'score' => $scoreValue,
                ]);
            }
        }

        return [$subjectId, $classroomId];
    }

    private function abortValidation(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    public function students(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $students = collect();
        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $students = Student::query()
                ->with('user')
                ->where('classroom_id', $classroomId)
                ->orderBy('nisn')
                ->get();
        }

        return view('guru_mapel.grades.students', compact(
            'guru', 'subjects', 'classrooms', 'subjectId', 'classroomId', 'students', 'validSelection',
        ));
    }

    public function studentHistory(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');
        $studentId = (int) $request->integer('student_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $student = Student::query()
            ->with('user')
            ->whereKey($studentId)
            ->where('classroom_id', $classroomId)
            ->firstOrFail();

        $grades = Grade::query()
            ->with(['classroom', 'subject'])
            ->where('guru_mapel_id', $guru->id)
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->get();

        $subject = $grades->first()?->subject ?? $guru->subjects()->whereKey($subjectId)->first();

        return view('guru_mapel.grades.student-history', compact('guru', 'subject', 'student', 'grades'));
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$rows, $breakdownMap] = $this->gradeRowsWithBreakdown($request);

        return Excel::download(new GradesExport($rows, $breakdownMap), 'daftar-nilai.xlsx');
    }

    public function exportPdf(Request $request): Response
    {
        [$rows, $breakdownMap] = $this->gradeRowsWithBreakdown($request);
        $subject = $rows->first()?->subject;
        $classroom = $rows->first()?->classroom;

        $pdf = Pdf::loadView('guru_mapel.grades.print', compact('rows', 'subject', 'classroom', 'breakdownMap'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('daftar-nilai.pdf');
    }

    /**
     * Ambil seluruh nilai milik guru pada kombinasi mapel-kelas yang diampu
     * (di-otorisasi), lengkap dengan breakdown per kategori untuk tiap siswa.
     * Dipakai bersama export Excel & PDF.
     *
     * @return array{0: Collection<int, Grade>, 1: Collection<int, array<string, mixed>>}
     */
    private function gradeRowsWithBreakdown(Request $request): array
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');
        $semesterId = $request->filled('semester_id')
            ? (int) $request->integer('semester_id')
            : (Semester::getActive()?->id ?? 0);

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $rows = Grade::query()
            ->with(['student.user', 'classroom', 'subject'])
            ->where('guru_mapel_id', $guru->id)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->orderByDesc('created_at')
            ->get();

        $studentIds = $rows->pluck('student_id')->unique()->values();

        if ($studentIds->isEmpty() || $semesterId === 0) {
            return [$rows, collect()];
        }

        $studentGrades = SubjectGrade::query()
            ->with('examType')
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        $attendanceByStudent = SubjectAttendance::query()
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $breakdownMap = $studentIds->mapWithKeys(function (int $studentId) use ($studentGrades, $attendanceByStudent) {
            $breakdown = $this->breakdownByType(
                $studentGrades->get($studentId, collect()),
                $attendanceByStudent->get($studentId)
            );

            return [$studentId => $breakdown];
        });

        return [$rows, $breakdownMap];
    }
}
