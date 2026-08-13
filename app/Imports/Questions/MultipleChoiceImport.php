<?php

namespace App\Imports\Questions;

use App\Models\Question;

class MultipleChoiceImport extends BaseTypeImport
{
    public function type(): string
    {
        return Question::TYPE_MULTIPLE_CHOICE;
    }

    public function dataSheetName(): string
    {
        return 'Data Pilihan Ganda Banyak';
    }

    protected function typeLabel(): string
    {
        return 'Pilihan Ganda Banyak';
    }

    protected function aliases(): array
    {
        return [
            'subject' => ['mata_pelajaran', 'mapel', 'pelajaran', 'subject', 'subject_name'],
            'question' => ['pertanyaan', 'soal', 'question', 'question_text', 'teks_soal'],
            'answer' => ['kunci_jawaban', 'jawaban', 'answer', 'answer_key', 'kunci'],
            'weight' => ['bobot', 'poin', 'score', 'score_weight', 'bobot_soal', 'nilai'],
            'option_A' => ['opsi_a', 'option_a', 'a', 'opsi_1', 'pilihan_a'],
            'option_B' => ['opsi_b', 'option_b', 'b', 'opsi_2', 'pilihan_b'],
            'option_C' => ['opsi_c', 'option_c', 'c', 'opsi_3', 'pilihan_c'],
            'option_D' => ['opsi_d', 'option_d', 'd', 'opsi_4', 'pilihan_d'],
            'option_E' => ['opsi_e', 'option_e', 'e', 'opsi_5', 'pilihan_e'],
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
        $answer = strtoupper($this->normalizeText($resolved['answer'] !== null ? ($rowData[$resolved['answer']] ?? null) : null));

        $options = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            $options[$letter] = $this->normalizeText($resolved['option_'.$letter] !== null ? ($rowData[$resolved['option_'.$letter]] ?? null) : null);
        }

        if ($question === '' && $subject === '' && $answer === ''
            && $weight === 10.0
            && collect($options)->every(fn (string $value) => $value === '')) {
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

        $nonEmpty = collect($options)->filter(fn (string $value) => $value !== '')->keys()->all();
        $letters = array_values(array_filter(array_map('trim', explode(',', $answer)), fn (string $letter) => $letter !== ''));

        if (count($nonEmpty) < 2) {
            $errors[] = 'Minimal 2 opsi jawaban harus diisi.';
        } elseif (empty($letters)) {
            $errors[] = 'Kunci jawaban wajib diisi (contoh: A,C).';
        } else {
            foreach ($letters as $letter) {
                if (! in_array($letter, $nonEmpty, true)) {
                    $errors[] = "Kunci jawaban {$letter} tidak merujuk opsi yang terisi.";
                }
            }
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
                'options' => collect($options)->filter(fn (string $value) => $value !== '')->all(),
                'answer_key' => $letters,
                'score_weight' => round($weight, 2),
            ],
        ];
    }
}
