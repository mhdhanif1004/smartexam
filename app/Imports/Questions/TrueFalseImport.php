<?php

namespace App\Imports\Questions;

use App\Models\Question;

class TrueFalseImport extends BaseTypeImport
{
    public function type(): string
    {
        return Question::TYPE_TRUE_FALSE;
    }

    public function dataSheetName(): string
    {
        return 'Data Benar Salah';
    }

    protected function typeLabel(): string
    {
        return 'Benar/Salah';
    }

    protected function aliases(): array
    {
        return [
            'subject' => ['mata_pelajaran', 'mapel', 'pelajaran', 'subject', 'subject_name'],
            'question' => ['pertanyaan', 'soal', 'question', 'question_text', 'teks_soal'],
            'answer' => ['kunci_jawaban', 'jawaban', 'answer', 'answer_key', 'kunci'],
            'weight' => ['bobot', 'poin', 'score', 'score_weight', 'bobot_soal', 'nilai'],
        ];
    }

    protected function requiredKeys(): array
    {
        return ['subject', 'question'];
    }

    protected function handleRow(array $rowData, array $resolved): ?array
    {
        $question = $this->normalizeText($rowData[$resolved['question']] ?? null);
        $subject = $this->normalizeText($rowData[$resolved['subject']] ?? null);
        $weight = $this->parseWeight($resolved['weight'] !== null ? ($rowData[$resolved['weight']] ?? null) : null);
        $answer = $this->normalizeText($resolved['answer'] !== null ? ($rowData[$resolved['answer']] ?? null) : null);

        if ($question === '' && $subject === '' && $answer === '' && $weight === 10.0) {
            return null;
        }

        $errors = [];
        $subjectModel = $subject === '' ? null : $this->resolveSubject($subject);

        if ($question === '') {
            $errors[] = 'Pertanyaan wajib diisi.';
        }
        if ($subject === '') {
            $errors[] = 'Mata pelajaran wajib diisi.';
        } elseif ($subjectModel === null) {
            $errors[] = "Mata pelajaran '{$subject}' tidak ditemukan di master data.";
        }
        if ($weight < 0 || $weight > 999.99) {
            $errors[] = 'Bobot soal harus angka antara 0 dan 999.99.';
        }

        $answerBool = null;
        if ($answer === '' || ! $this->parseBoolean($answer, $answerBool)) {
            $errors[] = 'Kunci jawaban harus diisi Benar, Salah, B, S, True, atau False.';
        }

        if (! empty($errors)) {
            return [
                'errors' => $errors,
                'subject' => $this->displayValue($subject),
                'question_text' => $question,
            ];
        }

        return [
            'errors' => [],
            'subject' => $subject,
            'question_text' => $question,
            'payload' => [
                'subject_id' => $subjectModel->id,
                'type' => $this->type(),
                'question_text' => $question,
                'options' => null,
                'answer_key' => $answerBool,
                'score_weight' => round($weight, 2),
            ],
        ];
    }
}
