<?php

namespace App\Imports\Questions;

use App\Models\Question;

class MatchingImport extends BaseTypeImport
{
    public function type(): string
    {
        return Question::TYPE_MATCHING;
    }

    public function dataSheetName(): string
    {
        return 'Data Menjodohkan';
    }

    protected function typeLabel(): string
    {
        return 'Menjodohkan';
    }

    protected function aliases(): array
    {
        return [
            'subject' => ['mata_pelajaran', 'mapel', 'pelajaran', 'subject', 'subject_name'],
            'question' => ['pertanyaan', 'soal', 'question', 'question_text', 'teks_soal'],
            'left' => ['kiri', 'kolom_kiri', 'left', 'kiri_matching', 'pilihan_kiri'],
            'right' => ['kanan', 'kolom_kanan', 'right', 'kanan_matching', 'pilihan_kanan'],
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
        $left = $resolved['left'] !== null ? $this->normalizeText($rowData[$resolved['left']] ?? null) : '';
        $right = $resolved['right'] !== null ? $this->normalizeText($rowData[$resolved['right']] ?? null) : '';

        $leftItems = $this->splitLines($left);
        $rightItems = $this->splitLines($right);

        if ($question === '' && $subject === '' && $left === '' && $right === '' && $weight === 10.0) {
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

        if (count($leftItems) < 2) {
            $errors[] = 'Menjodohkan memerlukan minimal 2 pasangan di kolom Kiri (satu per baris, pakai Alt+Enter).';
        } elseif (count($leftItems) !== count($rightItems)) {
            $errors[] = 'Jumlah pasangan kolom Kiri dan Kanan harus sama.';
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
                'options' => ['left' => $leftItems, 'right' => $rightItems],
                'answer_key' => collect(range(0, count($leftItems) - 1))
                    ->mapWithKeys(fn (int $index) => [chr(65 + $index) => (string) ($index + 1)])
                    ->all(),
                'score_weight' => round($weight, 2),
            ],
        ];
    }
}
