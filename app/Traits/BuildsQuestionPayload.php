<?php

namespace App\Traits;

use App\Models\Question;

/**
 * Trait bersama untuk membangun payload soal (options & answer_key) sesuai
 * jenis soal. Dipakai oleh Admin\QuestionController dan GuruMapel\QuestionController
 * agar logika konversi form → kolom persistensi tidak terduplikasi.
 */
trait BuildsQuestionPayload
{
    /**
     * Bangun payload soal (options & answer_key) sesuai jenis soal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function questionPayload(array $data): array
    {
        $options = null;
        $answerKey = null;

        switch ($data['type']) {
            case Question::TYPE_SINGLE_CHOICE:
                $options = $this->cleanOptions($data['single_options'] ?? []);
                $answerKey = $data['single_answer'];
                break;

            case Question::TYPE_MULTIPLE_CHOICE:
                $options = $this->cleanOptions($data['multiple_options'] ?? []);
                $answerKey = array_values(array_filter($data['multiple_answer'] ?? []));
                break;

            case Question::TYPE_TRUE_FALSE:
                $answerKey = (bool) ($data['true_false_answer'] ?? false);
                break;

            case Question::TYPE_MATCHING:
                [$left, $right] = $this->cleanPairs($data['matching_left'] ?? [], $data['matching_right'] ?? []);
                $options = ['left' => $left, 'right' => $right];
                $answerKey = collect(range(0, count($left) - 1))
                    ->mapWithKeys(fn (int $index) => [chr(65 + $index) => (string) ($index + 1)])
                    ->all();
                break;

            case Question::TYPE_ESSAY:
                $answerKey = $data['essay_answer'] ?? null;
                break;
        }

        return [
            'subject_id' => $data['subject_id'],
            'type' => $data['type'],
            'question_text' => $data['question_text'],
            'options' => $options,
            'answer_key' => $answerKey,
            'score_weight' => $data['score_weight'],
        ];
    }

    /**
     * @param  array<mixed>  $options
     * @return array<string, string>
     */
    protected function cleanOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
            ->all();
    }

    /**
     * @param  array<mixed>  $leftInput
     * @param  array<mixed>  $rightInput
     * @return array{0: array<string>, 1: array<string>}
     */
    protected function cleanPairs(array $leftInput, array $rightInput): array
    {
        $left = array_values($leftInput);
        $right = array_values($rightInput);

        $pairs = collect(range(0, max(count($left), count($right)) - 1))
            ->map(fn (int $index) => [trim((string) ($left[$index] ?? '')), trim((string) ($right[$index] ?? ''))])
            ->filter(fn (array $pair) => $pair[0] !== '' && $pair[1] !== '')
            ->values();

        return [
            $pairs->map(fn (array $pair) => $pair[0])->all(),
            $pairs->map(fn (array $pair) => $pair[1])->all(),
        ];
    }
}
