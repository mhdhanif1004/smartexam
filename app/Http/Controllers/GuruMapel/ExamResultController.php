<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Student;
use App\Traits\ScopesGuruMapel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamResultController extends Controller
{
    use ScopesGuruMapel;

    public function index(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $schedules = collect();
        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $className = Classroom::query()->whereKey($classroomId)->value('name');

            $schedules = ExamSchedule::query()
                ->with(['subject', 'examPeriod'])
                ->where('subject_id', $subjectId)
                ->when($className !== null, fn ($q) => $q->where('class_name', $className))
                ->whereHas('examSessions')
                ->orderByDesc('exam_date')
                ->orderBy('start_time')
                ->get();
        }

        return view('guru_mapel.exam-results.index', compact(
            'guru', 'subjects', 'classrooms', 'subjectId', 'classroomId', 'schedules', 'validSelection',
        ));
    }

    public function schedule(int $schedule): View
    {
        $guru = $this->currentGuru();
        $scheduleModel = $this->resolveAmpuSchedule($guru, $schedule);

        $students = $scheduleModel->participantStudents();

        $sessions = ExamSession::query()
            ->where('exam_schedule_id', $scheduleModel->id)
            ->get()
            ->keyBy('student_id');

        $results = ExamResult::query()
            ->whereIn('exam_session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy('exam_session_id');

        return view('guru_mapel.exam-results.schedule', compact(
            'scheduleModel', 'students', 'sessions', 'results',
        ));
    }

    public function student(int $schedule, int $student): View
    {
        $guru = $this->currentGuru();
        $scheduleModel = $this->resolveAmpuSchedule($guru, $schedule);

        $classroomId = Classroom::query()->where('name', $scheduleModel->class_name)->value('id');

        abort_unless($scheduleModel->hasParticipant($student), 403, 'Siswa bukan peserta ujian ini.');

        $studentModel = Student::query()->with('user')->whereKey($student)->firstOrFail();

        $session = ExamSession::query()
            ->where('exam_schedule_id', $scheduleModel->id)
            ->where('student_id', $student)
            ->with('examAnswers')
            ->first();

        $answers = $session?->examAnswers->keyBy('question_id') ?? collect();

        $questions = $scheduleModel->subject->questions()
            ->where('is_active', true)
            ->when($classroomId !== null, fn ($q) => $q->targetingClassroom($classroomId))
            ->get();

        $items = $questions->map(function (Question $question) use ($answers) {
            $answer = $answers->get($question->id);

            return [
                'question' => $question,
                'answer' => $answer,
                'student_display' => $this->formatStudentAnswer($question, $answer?->student_answer),
                'correct_display' => $this->formatCorrectAnswer($question),
            ];
        });

        $result = $session?->examResult;

        return view('guru_mapel.exam-results.student', compact(
            'scheduleModel', 'studentModel', 'items', 'session', 'result',
        ));
    }

    private function resolveAmpuSchedule($guru, int $scheduleId): ExamSchedule
    {
        $schedule = ExamSchedule::query()
            ->with(['subject', 'examPeriod'])
            ->findOrFail($scheduleId);

        $classroomId = Classroom::query()->where('name', $schedule->class_name)->value('id');

        abort_unless($classroomId !== null && $guru->isAmpu(subjectId: $schedule->subject_id, classroomId: $classroomId), 403);

        return $schedule;
    }

    private function formatStudentAnswer(Question $question, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'Tidak dijawab';
        }

        return match ($question->type) {
            Question::TYPE_SINGLE_CHOICE => is_array($value) ? (string) reset($value) : (string) $value,
            Question::TYPE_MULTIPLE_CHOICE => collect($value)->sort()->join(', '),
            Question::TYPE_TRUE_FALSE => $value ? 'Benar' : 'Salah',
            Question::TYPE_MATCHING => $this->formatMatching((array) $value, $question->options['right'] ?? []),
            Question::TYPE_ESSAY => (string) $value,
            default => is_array($value) ? json_encode($value) : (string) $value,
        };
    }

    private function formatCorrectAnswer(Question $question): string
    {
        $key = $question->answer_key;

        if ($key === null || $key === '' || $key === []) {
            return '—';
        }

        return match ($question->type) {
            Question::TYPE_SINGLE_CHOICE => is_array($key) ? (string) reset($key) : (string) $key,
            Question::TYPE_MULTIPLE_CHOICE => collect($key)->sort()->join(', '),
            Question::TYPE_TRUE_FALSE => $key ? 'Benar' : 'Salah',
            Question::TYPE_MATCHING => $this->formatMatching((array) $key, $question->options['right'] ?? []),
            Question::TYPE_ESSAY => (string) $key,
            default => is_array($key) ? json_encode($key) : (string) $key,
        };
    }

    private function formatMatching(array $value, array $right): string
    {
        if (array_is_list($value)) {
            return '';
        }

        $parts = [];

        foreach ($value as $left => $rightIndex) {
            $rightText = $right[(int) $rightIndex - 1] ?? $rightIndex;
            $parts[] = "{$left} → {$rightText}";
        }

        return implode('; ', $parts);
    }
}
