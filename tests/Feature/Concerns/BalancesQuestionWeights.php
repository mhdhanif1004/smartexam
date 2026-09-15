<?php

namespace Tests\Feature\Concerns;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Subject;

/**
 * Perbaiki data test agar mematuhi invariant bobot soal (total per
 * mapel×kelas == 100). Soal harus di-attach ke pivot question_classroom —
 * QuestionWeightService menghitung lewat pivot, bukan sekadar subject_id.
 */
trait BalancesQuestionWeights
{
    /**
     * Siapkan soal aktif total bobot 100 untuk satu kombinasi mapel×kelas.
     */
    protected function seedBalancedQuestions(Subject $subject, Classroom $classroom, int $potongan = 50): array
    {
        $questions = [
            Question::factory()->create([
                'subject_id' => $subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'score_weight' => $potongan,
                'is_active' => true,
            ]),
            Question::factory()->create([
                'subject_id' => $subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'score_weight' => 100 - $potongan,
                'is_active' => true,
            ]),
        ];

        foreach ($questions as $question) {
            $question->classrooms()->attach($classroom->id);
        }

        return $questions;
    }
}
