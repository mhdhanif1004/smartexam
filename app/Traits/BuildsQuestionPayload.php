<?php

namespace App\Traits;

use App\Models\Question;
use Illuminate\Http\UploadedFile;

/**
 * Trait bersama untuk membangun payload soal (options & answer_key) sesuai
 * jenis soal. Dipakai oleh Admin\QuestionController dan GuruMapel\QuestionController
 * agar logika konversi form → kolom persistensi tidak terduplikasi.
 *
 * Shape options (mixed): opsi tanpa gambar tetap string murni; opsi bergambar
 * menjadi array{text: string, image: ?string}. ATURAN MUTLAK: urutan/index
 * array tidak boleh berubah — matching (left[i]/right[i]) adalah kontrak
 * grading via answer_key & student_answer index-based.
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
                $options = $this->buildKeyedOptions($data['single_options'] ?? [], 'single', $data);
                $answerKey = $data['single_answer'];
                break;

            case Question::TYPE_MULTIPLE_CHOICE:
                $options = $this->buildKeyedOptions($data['multiple_options'] ?? [], 'multiple', $data);
                $answerKey = array_values(array_filter($data['multiple_answer'] ?? []));
                break;

            case Question::TYPE_TRUE_FALSE:
                $options = $this->buildTrueFalseOptions($data);
                $answerKey = (bool) ($data['true_false_answer'] ?? false);
                break;

            case Question::TYPE_MATCHING:
                [$left, $right] = $this->buildMatchingPairs($data);
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
     * Bangun options single/multiple_choice: key huruf A-E, nilai string
     * murni ATAU objek {text,image} bila opsi tsb (baru/existing) bergambar.
     * Key & urutan insertion dipertahankan persis dari input.
     *
     * @param  array<mixed>  $optionInputs
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildKeyedOptions(array $optionInputs, string $kind, array $data): array
    {
        $options = [];

        foreach ($optionInputs as $key => $value) {
            $text = trim((string) $value);

            if ($text === '') {
                continue;
            }

            $options[(string) $key] = $this->resolveOptionValue($text, $kind, (string) $key, $data);
        }

        return $options;
    }

    /**
     * Nilai final sebuah opsi berbasis gambar: string murni, atau objek
     * {text, image}. Urutan:
     * 1. File baru di-upload → optimize (QuestionImageOptimizer) → objek.
     * 2. Checkbox remove dicentang → string murni (gambar di-buang).
     * 3. Ada path gambar existing (hidden field) → objek (todo path lama).
     * 4. Tanpa gambar → string murni (perilaku lama, KANAN untuk soal tanpa gambar).
     *
     * @param  array<string, mixed>  $data
     * @return string|array{text: string, image: ?string}
     */
    protected function resolveOptionValue(string $text, string $kind, string $key, array $data): string|array
    {
        $fileKey = "{$kind}_options_image.{$key}";
        $existingKey = "existing_{$kind}_options_image.{$key}";
        $removeKey = "remove_{$kind}_options_image.{$key}";

        $file = data_get($data, $fileKey);
        if ($file instanceof UploadedFile) {
            return [
                'text' => $text,
                'image' => $this->imageOptimizer->optimize($file),
            ];
        }

        $remove = (bool) data_get($data, $removeKey, false);
        if ($remove) {
            return $text;
        }

        $existing = data_get($data, $existingKey);
        if (is_string($existing) && filled($existing)) {
            return [
                'text' => $text,
                'image' => $existing,
            ];
        }

        return $text;
    }

    /**
     * Bangun options matching: list left & right, tiap elemen string murni
     * atau objek {text,image}. Transformasi PER-ELEMEN dengan index yang sama
     * — tidak boleh reorder/filter yang menggeser posisi (kontrak grading).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<mixed>, 1: array<mixed>}
     */
    protected function buildMatchingPairs(array $data): array
    {
        $left = array_values($data['matching_left'] ?? []);
        $right = array_values($data['matching_right'] ?? []);

        $count = max(count($left), count($right));

        $leftResult = [];
        $rightResult = [];

        for ($i = 0; $i < $count; $i++) {
            $leftText = trim((string) ($left[$i] ?? ''));
            $rightText = trim((string) ($right[$i] ?? ''));

            $leftVal = $this->resolveMatchingSide($leftText, 'left', (string) $i, $data);
            $rightVal = $this->resolveMatchingSide($rightText, 'right', (string) $i, $data);

            $leftEmpty = $leftText === '' && (! is_array($leftVal) || empty($leftVal['image']));
            $rightEmpty = $rightText === '' && (! is_array($rightVal) || empty($rightVal['image']));

            if ($leftEmpty && $rightEmpty) {
                continue;
            }

            $leftResult[] = $leftVal === '' ? '' : $leftVal;
            $rightResult[] = $rightVal === '' ? '' : $rightVal;
        }

        return [$leftResult, $rightResult];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string|array{text: string, image: ?string}
     */
    protected function resolveMatchingSide(string $text, string $side, string $index, array $data): string|array
    {
        $fileKey = "matching_{$side}_image.{$index}";
        $existingKey = "existing_matching_{$side}_image.{$index}";
        $removeKey = "remove_matching_{$side}_image.{$index}";

        $file = data_get($data, $fileKey);
        if ($file instanceof UploadedFile) {
            return ['text' => $text, 'image' => $this->imageOptimizer->optimize($file)];
        }

        if ((bool) data_get($data, $removeKey, false)) {
            return $text;
        }

        $existing = data_get($data, $existingKey);
        if (is_string($existing) && filled($existing)) {
            return ['text' => $text, 'image' => $existing];
        }

        return $text;
    }

    /**
     * Options true_false: null selama tidak ada gambar true/false; diisi
     * objek {"true":{text,image}, "false":{text,image}} saat ada upload.
     * Label teks tetap "Benar"/"Salah" (hardcoded, konsisten dengan view).
     *
     * @param  array<string, mixed>  $data
     * @return array{true: array{text: string, image: ?string}, false: array{text: string, image: ?string}}|null
     */
    protected function buildTrueFalseOptions(array $data): ?array
    {
        $true = $this->resolveTrueFalseSide('Benar', 'true', $data);
        $false = $this->resolveTrueFalseSide('Salah', 'false', $data);

        if (is_string($true) && is_string($false)) {
            return null;
        }

        $true = is_string($true) ? ['text' => 'Benar', 'image' => null] : $true;
        $false = is_string($false) ? ['text' => 'Salah', 'image' => null] : $false;

        return ['true' => $true, 'false' => $false];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string|array{text: string, image: ?string}
     */
    protected function resolveTrueFalseSide(string $text, string $side, array $data): string|array
    {
        $fileKey = "true_false_image.{$side}";
        $existingKey = "existing_true_false_image.{$side}";
        $removeKey = "remove_true_false_image.{$side}";

        $file = data_get($data, $fileKey);
        if ($file instanceof UploadedFile) {
            return ['text' => $text, 'image' => $this->imageOptimizer->optimize($file)];
        }

        if ((bool) data_get($data, $removeKey, false)) {
            return $text;
        }

        $existing = data_get($data, $existingKey);
        if (is_string($existing) && filled($existing)) {
            return ['text' => $text, 'image' => $existing];
        }

        return $text;
    }
}
