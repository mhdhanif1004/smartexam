<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Question;
use Illuminate\Validation\Validator;

trait ValidatesQuestionTypes
{
    /**
     * Validasi tambahan yang spesifik per jenis soal. Dijalankan setelah
     * aturan dasar sehingga pesan kesalahan bisa disesuaikan per jenis.
     *
     * @return array<int, \Closure(Validator): void>
     */
    protected function questionTypeRules(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('type');

                match ($type) {
                    Question::TYPE_SINGLE_CHOICE => $this->validateChoice($validator, 'single_options', 'single_answer', false),
                    Question::TYPE_MULTIPLE_CHOICE => $this->validateChoice($validator, 'multiple_options', 'multiple_answer', true),
                    Question::TYPE_TRUE_FALSE => $this->validateTrueFalse($validator),
                    Question::TYPE_MATCHING => $this->validateMatching($validator),
                    default => null, // essay: tidak ada opsi yang wajib diisi
                };
            },
        ];
    }

    private function validateChoice(Validator $validator, string $optionsField, string $answerField, bool $multiple): void
    {
        $filledOptions = collect($this->input($optionsField) ?? [])
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
            ->all();

        if (count($filledOptions) < 2) {
            $validator->errors()->add($optionsField, 'Minimal 2 opsi jawaban harus diisi.');
        }

        if ($multiple) {
            $answers = array_values(array_filter((array) ($this->input($answerField) ?? []), fn ($value) => filled($value)));

            if ($answers === []) {
                $validator->errors()->add($answerField, 'Centang minimal satu opsi sebagai jawaban yang benar.');
            }
        } else {
            $answer = $this->input($answerField);
            $answers = ($answer === null || trim((string) $answer) === '') ? [] : [$answer];

            if ($answers === []) {
                $validator->errors()->add($answerField, 'Pilih satu opsi sebagai kunci jawaban.');
            }
        }

        foreach ($answers as $letter) {
            if (! array_key_exists((string) $letter, $filledOptions)) {
                $validator->errors()->add($answerField, 'Kunci jawaban ('.(string) $letter.') harus memiliki teks opsi yang diisi.');
            }
        }
    }

    private function validateTrueFalse(Validator $validator): void
    {
        $answer = $this->input('true_false_answer');

        if ($answer === null || ! in_array((string) $answer, ['1', '0'], true)) {
            $validator->errors()->add('true_false_answer', 'Pilih salah satu kunci jawaban: Benar atau Salah.');
        }
    }

    private function validateMatching(Validator $validator): void
    {
        $clean = fn (array $values): array => array_values(array_map(
            fn ($value) => trim((string) $value),
            array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')
        ));

        $left = $clean((array) ($this->input('matching_left') ?? []));
        $right = $clean((array) ($this->input('matching_right') ?? []));

        if (count($left) < 2 || count($right) < 2) {
            $validator->errors()->add('matching_left', 'Minimal 2 pasangan (kolom kiri dan kanan) harus diisi.');

            return;
        }

        if (count($left) !== count($right)) {
            $validator->errors()->add('matching_right', 'Jumlah item kolom kiri dan kanan harus sama.');
        }
    }
}
