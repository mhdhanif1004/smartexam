<?php

namespace App\Services;

use App\Models\ExamAnswer;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubjectGrade;

class ExamGradingService
{
    public const PASSING_RATIO = 0.7;

    public const CBT_TITLE = 'CBT';

    /**
     * Nilai satu jawaban sesuai jenis soal.
     *
     * Soal essay tidak dinilai otomatis (asumsi: nilai essay dikoreksi
     * manual oleh pengawas/guru, sehingga skor sementara dibiarkan null).
     *
     * @return array{score: float|null, is_correct: bool|null}
     */
    public function grade(Question $question, mixed $answer): array
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return ['score' => null, 'is_correct' => null];
        }

        $isCorrect = match ($question->type) {
            Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_MATCHING => $this->sameList($answer, $question->answer_key),
            Question::TYPE_TRUE_FALSE => $this->sameBool($answer, $question->answer_key),
            default => null, // essay
        };

        if ($isCorrect === null) {
            return ['score' => null, 'is_correct' => null];
        }

        return [
            'score' => $isCorrect ? (float) $question->score_weight : 0.0,
            'is_correct' => $isCorrect,
        ];
    }

    /**
     * Selesaikan sesi: tandai selesai, hitung skor semua jawaban objektif,
     * lalu buat/update record hasil ujian.
     *
     * Asumsi: essay tidak ikut dihitung otomatis (score null) sampai
     * dilakukan koreksi manual; is_passed dihitung dari soal objektif.
     */
    public function finalize(ExamSession $session, ?ExamSchedule $schedule = null): ExamResult
    {
        $schedule ??= $session->examSchedule;

        $classroomId = Student::query()
            ->whereKey($session->student_id)
            ->value('classroom_id');

        $questions = $schedule->subject->questions()
            ->where('is_active', true)
            ->when($classroomId !== null, fn ($query) => $query->targetingClassroom($classroomId))
            ->get()
            ->keyBy('id');
        $answers = $session->examAnswers()->get();

        $totalScore = 0.0;
        $scoredAnswers = [];

        foreach ($answers as $answer) {
            $question = $questions->get($answer->question_id);
            if (! $question instanceof Question) {
                continue;
            }

            ['score' => $score, 'is_correct' => $isCorrect] = $this->grade($question, $answer->student_answer);
            $scoredAnswers[] = [
                'id' => $answer->id,
                'exam_session_id' => $answer->exam_session_id,
                'question_id' => $answer->question_id,
                'score' => $score,
                'is_correct' => $isCorrect,
            ];

            if ($score !== null) {
                $totalScore += (float) $score;
            }
        }

        if ($scoredAnswers !== []) {
            ExamAnswer::query()->upsert($scoredAnswers, ['id'], ['score', 'is_correct']);
        }

        $maxScore = $questions
            ->where('type', '!=', Question::TYPE_ESSAY)
            ->sum(fn (Question $question) => (float) $question->score_weight);

        $isPassed = $maxScore > 0 && $totalScore >= $maxScore * self::PASSING_RATIO;

        $session->update([
            'status' => ExamSession::STATUS_COMPLETED,
            'finished_at' => $session->finished_at ?? now(),
        ]);

        $examResult = ExamResult::updateOrCreate(
            ['exam_session_id' => $session->id],
            ['total_score' => round($totalScore, 2), 'is_passed' => $isPassed],
        );

        // Sync ke subject_grades (nilai per jenis ujian) — terpisah dari
        // exam_results yang tetap dipertahankan utuh. exam_type diambil dari
        // ExamPeriod; fallback UAS untuk periode lama yang belum ke-tag.
        $this->syncSubjectGrade($session, $schedule, round($totalScore, 2));

        return $examResult;
    }

    /**
     * Upsert baris subject_grades untuk hasil ujian CBT.
     *
     * Identitas baris memakai uk_cbt (student_id + exam_schedule_id), bukan
     * title. title diisi konstan 'CBT' karena kolom title NOT NULL di skema
     * (penutup celah uk_manual); nilai ini tidak akan bentrok dengan baris
     * manual yang selalu memakai title bebas (UH 1, Remidi, dst).
     */
    private function syncSubjectGrade(ExamSession $session, ExamSchedule $schedule, float $totalScore): void
    {
        $guruMapelId = $schedule->subject?->guruMapels()?->first()?->id;
        $classroomId = $session->student?->classroom_id
            ?? Student::query()->whereKey($session->student_id)->value('classroom_id');

        if ($classroomId === null) {
            return;
        }

        $examTypeId = $schedule->examPeriod?->exam_type_id
            ?? ExamType::query()->where('code', 'uas')->value('id');

        SubjectGrade::updateOrCreate(
            [
                'student_id' => $session->student_id,
                'exam_schedule_id' => $schedule->id,
            ],
            [
                'classroom_id' => $classroomId,
                'subject_id' => $schedule->subject_id,
                'guru_mapel_id' => $guruMapelId,
                'semester_id' => $this->activeSemesterId(),
                'exam_type_id' => $examTypeId,
                'title' => self::CBT_TITLE,
                'score' => $totalScore,
                'source' => SubjectGrade::SOURCE_CBT,
                'is_override' => false,
                'taken_at' => now()->toDateString(),
            ]
        );
    }

    private function activeSemesterId(): ?int
    {
        return Semester::getActive()?->id;
    }

    private function sameBool(mixed $answer, mixed $key): bool
    {
        return (bool) $answer === (bool) $key;
    }

    private function sameList(mixed $answer, mixed $key): bool
    {
        return $this->normalizedList($answer) === $this->normalizedList($key);
    }

    /**
     * Normalisasi jawaban ke daftar string terurut agar perbandingan
     * (single_choice, multiple_choice, dan matching) deterministik.
     *
     * @return array<int, string>
     */
    private function normalizedList(mixed $value): array
    {
        if (! is_array($value)) {
            return [(string) $value];
        }

        $items = array_map(fn (mixed $item) => (string) $item, $value);

        if (array_is_list($items)) {
            sort($items);
        } else {
            ksort($items);
            $items = array_values($items);
        }

        return $items;
    }
}
