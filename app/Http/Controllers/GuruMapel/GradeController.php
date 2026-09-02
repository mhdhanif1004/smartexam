<?php

namespace App\Http\Controllers\GuruMapel;

use App\Exports\GradesExport;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Traits\ScopesGuruMapel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
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

        $students = collect();
        $savedGrades = collect();
        $classroom = null;
        $rows = collect();

        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $students = Student::query()
                ->with('user')
                ->where('classroom_id', $classroomId)
                ->orderBy('nisn')
                ->get();

            $classroom = Classroom::query()->find($classroomId);

            $savedGrades = Grade::query()
                ->where('guru_mapel_id', $guru->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->get()
                ->keyBy('student_id');

            // Hasil ujian CBT terakhir per siswa untuk kombinasi mapel-kelas ini.
            $latestResults = $this->latestExamResults($subjectId, $students);

            // Sinkronkan nilai otomatis dari ExamResult ke tabel grades selama
            // belum ada koreksi manual (is_override) — nilai guru tetap menang.
            $this->syncAutoScores($guru, $subjectId, $classroomId, $students, $savedGrades, $latestResults);

            $savedGrades = Grade::query()
                ->where('guru_mapel_id', $guru->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->get()
                ->keyBy('student_id');

            $rows = $students->map(function (Student $student) use ($savedGrades, $latestResults) {
                $grade = $savedGrades->get($student->id);
                $override = $grade !== null && $grade->is_override;
                $autoResult = $latestResults->get($student->id)?->examResult;

                return [
                    'student' => $student,
                    'note' => $grade?->note,
                    'score' => $override
                        ? (float) $grade->score
                        : ($autoResult !== null ? (float) $autoResult->total_score : null),
                    'source' => $override ? 'override' : ($autoResult !== null ? 'auto' : 'none'),
                ];
            })->values();
        }

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $guruId = $guru->id;

        return view('guru_mapel.grades.index', compact(
            'guru', 'guruId', 'subjects', 'classrooms', 'subjectId', 'classroomId',
            'students', 'savedGrades', 'validSelection', 'classroom', 'rows',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $classroomId = (int) $validated['classroom_id'];

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $studentIds = Student::query()
            ->where('classroom_id', $classroomId)
            ->pluck('id');

        $scores = $request->input('score', []);
        $notes = $request->input('note', []);

        $validator = Validator::make($request->all(), [
            'score' => ['required', 'array'],
            'score.*' => ['required', 'numeric', 'between:0,100.00'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($scores, $studentIds) {
            foreach (array_keys($scores) as $studentId) {
                if (! $studentIds->contains((int) $studentId)) {
                    $validator->errors()->add('score', 'Terdapat siswa yang bukan bagian dari kelas ini.');

                    return;
                }
            }
        })->validate();

        foreach ($scores as $studentId => $score) {
            Grade::updateOrCreate(
                [
                    'guru_mapel_id' => $guru->id,
                    'student_id' => (int) $studentId,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                ],
                [
                    'score' => $score,
                    'note' => $notes[(int) $studentId] ?? null,
                    'is_override' => true,
                ]
            );
        }

        return back()->with('success', 'Nilai berhasil disimpan.');
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

        $grade = Grade::query()
            ->where('guru_mapel_id', $guru->id)
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->first();

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
            'subjectId', 'classroomId', 'studentId', 'allQuestions',
        ));
    }

    /**
     * Simpan koreksi skor per jawaban dari halaman detail. Dua input terpisah:
     * - `scores[answer_id]`: update skor jawaban yang SUDAH ada di DB.
     * - `new_scores[question_id]`: buat ExamAnswer BARU untuk soal yang tidak
     *   dijawab siswa — row baru hanya tercipta saat guru benar-benar mengisi
     *   koreksi dan klik Simpan (bukan saat halaman dibuka).
     * Total nilai dihitung ulang dari seluruh skor per soal sesi tersebut.
     */
    public function saveScores(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'session_id' => ['required', 'integer'],
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

        $student = Student::query()
            ->with('user')
            ->whereKey($studentId)
            ->where('classroom_id', $classroomId)
            ->firstOrFail();

        $session = ExamSession::query()
            ->with(['examSchedule', 'examAnswers.question'])
            ->findOrFail((int) $validated['session_id']);

        // Sesi harus benar-benar milik siswa ini dan berasal dari jadwal dengan
        // mapel yang diampu; pencocokan tidak lagi memakai class_name (sering
        // kosong di produksi) melainkan keanggotaan siswa pada jadwal via sesi.
        $sessionMatches = $session->student_id === $studentId
            && $session->examSchedule !== null
            && (int) $session->examSchedule->subject_id === $subjectId
            && ExamSchedule::query()
                ->whereKey($session->examSchedule->id)
                ->whereHas('examSessions', fn ($q) => $q->where('student_id', $studentId))
                ->exists();

        abort_unless($sessionMatches, 403, 'Sesi ujian tidak sesuai dengan mapel-kelas siswa ini.');

        // --- 1. Validasi & simpan skor jawaban YANG SUDAH ADA ---
        $answers = $session->examAnswers->keyBy('id');
        $scores = $request->input('scores', []);

        $validator = Validator::make($request->all(), [
            'scores.*' => ['nullable', 'numeric', 'min:0'],
            'new_scores.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($scores, $answers, $request, $subjectId, $student) {
            foreach ($scores as $answerId => $value) {
                $answer = $answers->get((int) $answerId);

                if ($answer === null) {
                    $validator->errors()->add('scores', 'Terdapat jawaban yang bukan bagian dari sesi ini.');

                    return;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $max = (float) ($answer->question?->score_weight ?? 0);

                if ((float) $value > $max) {
                    $validator->errors()->add('scores.'.$answerId, 'Skor maksimal adalah '.$max.' untuk soal ini.');
                }
            }

            // Validasi new_scores: question_id harus valid untuk sesi ini
            $newScores = $request->input('new_scores', []);
            $existingQuestionIds = $answers->pluck('question_id')->toArray();

            foreach ($newScores as $questionId => $value) {
                $questionIdInt = (int) $questionId;

                // Sudah ada jawaban asli → jangan proses sebagai new_scores
                if (in_array($questionIdInt, $existingQuestionIds, true)) {
                    $validator->errors()->add('new_scores.'.$questionId, 'Soal ini sudah memiliki jawaban di database.');

                    return;
                }

                // Pastikan soal benar milik sesi ini
                $questionExists = Question::query()
                    ->where('id', $questionIdInt)
                    ->where('subject_id', $subjectId)
                    ->targetingClassroom((int) $student->classroom_id)
                    ->exists();

                if (! $questionExists) {
                    $validator->errors()->add('new_scores.'.$questionId, 'Soal tidak ditemukan untuk sesi ini.');

                    return;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                // Cari bobot maksimal soal
                $question = Question::find($questionIdInt);
                $max = $question ? (float) $question->score_weight : 0;

                if ((float) $value > $max) {
                    $validator->errors()->add('new_scores.'.$questionId, 'Skor maksimal adalah '.$max.' untuk soal ini.');
                }
            }
        })->validate();

        // Update skor jawaban yang sudah ada
        $scores = $request->input('scores', []);
        foreach ($scores as $answerId => $value) {
            ExamAnswer::query()
                ->whereKey((int) $answerId)
                ->where('exam_session_id', $session->id)
                ->update(['score' => $value === null || $value === '' ? null : (float) $value]);
        }

        // --- 2. Buat ExamAnswer BARU hanya untuk new_scores (koreksi guru
        //     untuk soal yang tidak dijawab siswa) ---
        $newScores = $request->input('new_scores', []);
        $classroomIdForQuestion = (int) $session->examSchedule->classroom_id;

        foreach ($newScores as $questionId => $value) {
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

        // Reload examAnswers setelah insert baru
        $session->load('examAnswers');

        $total = (float) $session->examAnswers()->sum('score');

        Grade::updateOrCreate(
            [
                'guru_mapel_id' => $guru->id,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
            ],
            [
                'score' => round($total, 2),
                'note' => $validated['note'] ?? null,
                'is_override' => true,
            ]
        );

        return redirect()
            ->route('guru_mapel.grades.detail', [
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
                'student_id' => $studentId,
            ])
            ->with('success', 'Skor jawaban siswa berhasil disimpan.');
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
        $rows = $this->gradeRows($request);

        return Excel::download(new GradesExport($rows), 'daftar-nilai.xlsx');
    }

    public function exportPdf(Request $request): Response
    {
        $rows = $this->gradeRows($request);
        $subject = $rows->first()?->subject;
        $classroom = $rows->first()?->classroom;

        $pdf = Pdf::loadView('guru_mapel.grades.print', compact('rows', 'subject', 'classroom'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('daftar-nilai.pdf');
    }

    /**
     * Ambil seluruh nilai milik guru pada kombinasi mapel-kelas yang diampu
     * (di-otorisasi). Dipakai bersama export Excel & PDF.
     *
     * @return Collection<int, Grade>
     */
    private function gradeRows(Request $request): Collection
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        return Grade::query()
            ->with(['student.user', 'classroom', 'subject'])
            ->where('guru_mapel_id', $guru->id)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * ExamResult (hasil ujian CBT) yang terakhir per siswa pada kombinasi
     * mapel-kelas tertentu. Pencocokan jadwal memakai subject_id + siswa yang
     * bersesi (bukan class_name, yang sering kosong pada jadwal produksi).
     *
     * @return Collection<int, ExamSession> keyed by student_id
     */
    private function latestExamResults(int $subjectId, Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        $studentIds = $students->pluck('id');

        $scheduleIds = ExamSchedule::query()
            ->where('subject_id', $subjectId)
            ->whereHas('examSessions', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->pluck('id');

        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        return ExamSession::query()
            ->with('examResult')
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('exam_schedule_id', $scheduleIds)
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_TIMED_OUT])
            ->whereHas('examResult')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map->first();
    }

    /**
     * Isi nilai otomatis hasil ujian CBT ke tabel grades (is_override=false)
     * selama belum ada koreksi manual. Nilai yang sudah di-override guru
     * tidak pernah tertimpa oleh sinkronisasi ini.
     */
    private function syncAutoScores(
        $guru,
        int $subjectId,
        int $classroomId,
        Collection $students,
        Collection $savedGrades,
        Collection $latestResults
    ): void {
        foreach ($students as $student) {
            $grade = $savedGrades->get($student->id);

            if ($grade !== null && $grade->is_override) {
                continue;
            }

            $result = $latestResults->get($student->id)?->examResult;

            if ($result === null) {
                continue;
            }

            Grade::updateOrCreate(
                [
                    'guru_mapel_id' => $guru->id,
                    'student_id' => $student->id,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                ],
                [
                    'score' => $result->total_score,
                    'note' => $grade?->note,
                    'is_override' => false,
                ]
            );
        }
    }
}
