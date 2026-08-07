<?php

namespace App\Imports;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class QuestionsImport implements ToCollection, WithEvents, WithHeadingRow
{
    public string $headerError = '';

    /**
     * Baris valid yang siap disimpan.
     *
     * @var list<array{
     *     row:int,
     *     subject_id:int,
     *     type:string,
     *     question_text:string,
     *     options:?array,
     *     answer_key:mixed,
     *     score_weight:float,
     * }>
     */
    public array $validRows = [];

    /**
     * Baris yang gagal validasi.
     *
     * @var list<array{row:int, data:array<string,mixed>, errors:list<string>}>
     */
    public array $invalidRows = [];

    public int $toCreate = 0;

    public int $toUpdate = 0;

    private const SUBJECT_ALIASES = ['mata_pelajaran', 'mapel', 'pelajaran', 'subject', 'subject_name', 'mata_pelajaran_soal'];

    private const TYPE_ALIASES = ['jenis', 'tipe', 'type', 'jenis_soal', 'tipe_soal'];

    private const QUESTION_ALIASES = ['pertanyaan', 'soal', 'question', 'question_text', 'pertanyaan_soal', 'teks_soal'];

    private const ANSWER_ALIASES = ['jawaban', 'answer', 'answer_key', 'kunci', 'kunci_jawaban'];

    private const WEIGHT_ALIASES = ['bobot', 'score', 'score_weight', 'poin', 'bobot_soal', 'nilai'];

    private const LEFT_ALIASES = ['kiri', 'kolom_kiri', 'left', 'kiri_matching', 'pilihan_kiri'];

    private const RIGHT_ALIASES = ['kanan', 'kolom_kanan', 'right', 'kanan_matching', 'pilihan_kanan'];

    private const OPTION_ALIASES = [
        'A' => ['opsi_a', 'option_a', 'a', 'opsi_1', 'pilihan_a'],
        'B' => ['opsi_b', 'option_b', 'b', 'opsi_2', 'pilihan_b'],
        'C' => ['opsi_c', 'option_c', 'c', 'opsi_3', 'pilihan_c'],
        'D' => ['opsi_d', 'option_d', 'd', 'opsi_4', 'pilihan_d'],
        'E' => ['opsi_e', 'option_e', 'e', 'opsi_5', 'pilihan_e'],
    ];

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->detectHeaders($event->getReader()->getActiveSheet());
            },
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($this->headerError !== '') {
            return;
        }

        $keys = $rows->isNotEmpty() ? $rows->first()->keys()->all() : [];

        $subjectKey = $this->resolveKey($keys, self::SUBJECT_ALIASES);
        $typeKey = $this->resolveKey($keys, self::TYPE_ALIASES);
        $questionKey = $this->resolveKey($keys, self::QUESTION_ALIASES);

        if ($subjectKey === null || $typeKey === null || $questionKey === null) {
            $this->headerError = $this->missingHeaderMessage($subjectKey, $typeKey, $questionKey);

            return;
        }

        $answerKey = $this->resolveKey($keys, self::ANSWER_ALIASES);
        $weightKey = $this->resolveKey($keys, self::WEIGHT_ALIASES);
        $leftKey = $this->resolveKey($keys, self::LEFT_ALIASES);
        $rightKey = $this->resolveKey($keys, self::RIGHT_ALIASES);
        $optionKeys = [];

        foreach (self::OPTION_ALIASES as $letter => $aliases) {
            $optionKeys[$letter] = $this->resolveKey($keys, $aliases);
        }

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;
            $errors = [];

            $questionText = $this->normalizeText($rowData[$questionKey] ?? null);

            if ($questionText === '') {
                $errors[] = 'Pertanyaan wajib diisi.';
            }

            $subject = $this->resolveSubject($this->normalizeText($rowData[$subjectKey] ?? null));

            if ($subject === null) {
                $errors[] = 'Mata pelajaran tidak ditemukan di master data.';
            }

            $type = $this->normalizeType($this->normalizeText($rowData[$typeKey] ?? null));

            if ($type === null) {
                $errors[] = 'Jenis soal tidak dikenali. Gunakan salah satu: Pilihan Ganda, Pilihan Ganda Banyak, Benar/Salah, Menjodohkan, Essay.';
            }

            $weight = $weightKey !== null && $rowData[$weightKey] !== null && $rowData[$weightKey] !== ''
                ? (float) $this->normalizeText($rowData[$weightKey])
                : 10.0;

            if ($weight < 0 || $weight > 999.99) {
                $errors[] = 'Bobot soal harus angka antara 0 dan 999.99.';
            }

            $options = $this->collectOptions($rowData, $optionKeys);
            $answer = $answerKey !== null ? $this->normalizeText($rowData[$answerKey]) : '';
            $left = $leftKey !== null ? $this->normalizeText($rowData[$leftKey]) : '';
            $right = $rightKey !== null ? $this->normalizeText($rowData[$rightKey]) : '';

            $typeErrors = $type === null ? [] : $this->validateByType($type, $options, $answer, $left, $right);

            $errors = array_merge($errors, $typeErrors);

            $isEmptyRow = $questionText === ''
                && $subject === null
                && $type === null
                && $answer === ''
                && $left === ''
                && $right === ''
                && $weight === 10.0
                && collect($options)->filter(fn (string $value) => $value !== '')->isEmpty();

            if ($isEmptyRow) {
                continue;
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'subject' => $this->displayValue($rowData[$subjectKey] ?? null),
                        'type' => $this->displayValue($rowData[$typeKey] ?? null),
                        'question_text' => $questionText !== '' ? $questionText : $this->displayValue($rowData[$questionKey] ?? null),
                        'answer' => $answer,
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $this->toCreate++;

            $this->validRows[] = [
                'row' => $rowNumber,
                'subject_id' => $subject->id,
                'type' => $type,
                'question_text' => $questionText,
                'options' => $this->buildOptions($type, $options),
                'answer_key' => $this->buildAnswerKey($type, $options, $answer, $left, $right),
                'score_weight' => round($weight, 2),
            ];
        }
    }

    /**
     * @return array{created:int, updated:int, errors:list<string>}
     */
    public function persistRows(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($this->validRows as $validRow) {
            try {
                Question::create([
                    'subject_id' => $validRow['subject_id'],
                    'type' => $validRow['type'],
                    'question_text' => $validRow['question_text'],
                    'options' => $validRow['options'],
                    'answer_key' => $validRow['answer_key'],
                    'score_weight' => $validRow['score_weight'],
                    'is_active' => true,
                ]);

                $result['created']++;
            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$validRow['row']}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $options
     * @return list<string>
     */
    private function validateByType(string $type, array $options, string $answer, string $left, string $right): array
    {
        $errors = [];
        $nonEmpty = collect($options)->filter(fn (string $value) => $value !== '')->keys()->all();

        switch ($type) {
            case Question::TYPE_SINGLE_CHOICE:
                if (count($nonEmpty) < 2) {
                    $errors[] = 'Pilihan ganda memerlukan minimal 2 opsi terisi.';
                } elseif ($answer === '' || ! in_array($answer, $nonEmpty, true)) {
                    $errors[] = 'Jawaban harus berupa huruf opsi yang terisi (A-E).';
                }
                break;

            case Question::TYPE_MULTIPLE_CHOICE:
                if (count($nonEmpty) < 2) {
                    $errors[] = 'Pilihan ganda memerlukan minimal 2 opsi terisi.';
                    break;
                }

                $letters = array_filter(array_map('trim', explode(',', $answer)));

                if (empty($letters)) {
                    $errors[] = 'Jawaban wajib diisi (contoh: A,C).';
                    break;
                }

                foreach ($letters as $letter) {
                    if (! in_array(strtoupper($letter), $nonEmpty, true)) {
                        $errors[] = "Jawaban {$letter} tidak merujuk opsi yang terisi.";
                    }
                }
                break;

            case Question::TYPE_TRUE_FALSE:
                if (! $this->parseBoolean($answer, $parsed)) {
                    $errors[] = 'Jawaban Benar/Salah harus diisi Benar, Salah, B, S, True, atau False.';
                }
                break;

            case Question::TYPE_MATCHING:
                $leftItems = $this->splitLines($left);
                $rightItems = $this->splitLines($right);

                if (count($leftItems) < 2) {
                    $errors[] = 'Menjodohkan memerlukan minimal 2 pasangan di kolom Kiri.';
                } elseif (count($leftItems) !== count($rightItems)) {
                    $errors[] = 'Jumlah pasangan kolom Kiri dan Kanan harus sama.';
                }
                break;
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $options
     */
    private function buildOptions(string $type, array $options): ?array
    {
        if ($type === Question::TYPE_MATCHING) {
            return null;
        }

        if ($type === Question::TYPE_TRUE_FALSE || $type === Question::TYPE_ESSAY) {
            return null;
        }

        $clean = [];

        foreach ($options as $letter => $value) {
            if ($value !== '') {
                $clean[$letter] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, string>  $options
     */
    private function buildAnswerKey(string $type, array $options, string $answer, string $left, string $right): mixed
    {
        switch ($type) {
            case Question::TYPE_SINGLE_CHOICE:
                return $answer;

            case Question::TYPE_MULTIPLE_CHOICE:
                return array_values(array_filter(array_map(
                    fn (string $letter) => strtoupper(trim($letter)),
                    explode(',', $answer)
                )));

            case Question::TYPE_TRUE_FALSE:
                $this->parseBoolean($answer, $parsed);

                return $parsed;

            case Question::TYPE_MATCHING:
                $leftItems = $this->splitLines($left);

                return collect(range(0, count($leftItems) - 1))
                    ->mapWithKeys(fn (int $index) => [chr(65 + $index) => (string) ($index + 1)])
                    ->all();

            case Question::TYPE_ESSAY:
                return $answer !== '' ? $answer : null;
        }

        return null;
    }

    /**
     * Baca isi kolom opsi A-E dari sebuah baris.
     *
     * @param  array<string, mixed>  $rowData
     * @param  array<string, ?string>  $optionKeys
     * @return array<string, string>
     */
    private function collectOptions(array $rowData, array $optionKeys): array
    {
        $options = [];

        foreach ($optionKeys as $letter => $key) {
            $options[$letter] = $key !== null ? $this->normalizeText($rowData[$key] ?? null) : '';
        }

        return $options;
    }

    private function resolveSubject(string $name): ?Subject
    {
        if ($name === '') {
            return null;
        }

        return Subject::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    private function normalizeType(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = Str::lower(Str::slug($value, ' '));

        return match (true) {
            str_contains($normalized, 'pilihan ganda') && (str_contains($normalized, 'banyak') || str_contains($normalized, 'multiple')) => Question::TYPE_MULTIPLE_CHOICE,
            str_contains($normalized, 'pilihan ganda'), str_contains($normalized, 'single') => Question::TYPE_SINGLE_CHOICE,
            str_contains($normalized, 'benar'), str_contains($normalized, 'true'), str_contains($normalized, 'salah') => Question::TYPE_TRUE_FALSE,
            str_contains($normalized, 'jodoh'), str_contains($normalized, 'matching'), str_contains($normalized, 'pasangan') => Question::TYPE_MATCHING,
            str_contains($normalized, 'essay'), str_contains($normalized, 'esai'), str_contains($normalized, 'uraian'), str_contains($normalized, 'isian') => Question::TYPE_ESSAY,
            default => null,
        };
    }

    /**
     * @param  mixed  $value
     */
    private function parseBoolean(string $value, ?bool &$parsed): bool
    {
        $normalized = Str::lower(trim($value));

        $map = [
            'benar' => true, 'true' => true, 'b' => true, 'ya' => true, '1' => true,
            'salah' => false, 'false' => false, 's' => false, 'tidak' => false, '0' => false,
        ];

        if (! array_key_exists($normalized, $map)) {
            return false;
        }

        $parsed = $map[$normalized];

        return true;
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $value): array
    {
        return collect(preg_split('/[\r\n]+/', trim($value)))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function detectHeaders(Worksheet $sheet): void
    {
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $headers = [];

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $value = $sheet->getCellByColumnAndRow($col, 1)->getValue();
            if ($value === null || $value === '') {
                continue;
            }
            $headers[] = Str::slug((string) $value, '_');
        }

        if (empty($headers)) {
            $this->headerError = 'File tidak memiliki baris header yang valid.';

            return;
        }

        $subjectKey = $this->resolveKey($headers, self::SUBJECT_ALIASES);
        $typeKey = $this->resolveKey($headers, self::TYPE_ALIASES);
        $questionKey = $this->resolveKey($headers, self::QUESTION_ALIASES);

        if ($subjectKey === null || $typeKey === null || $questionKey === null) {
            $this->headerError = $this->missingHeaderMessage($subjectKey, $typeKey, $questionKey);
        }
    }

    private function missingHeaderMessage(?string $subjectKey, ?string $typeKey, ?string $questionKey): string
    {
        $missing = [];

        if ($subjectKey === null) {
            $missing[] = 'Mata Pelajaran';
        }
        if ($typeKey === null) {
            $missing[] = 'Jenis';
        }
        if ($questionKey === null) {
            $missing[] = 'Pertanyaan';
        }

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .'. Gunakan header sesuai template (Mata Pelajaran, Jenis, Pertanyaan).';
    }

    /**
     * @param  array<mixed>  $keys
     * @param  array<string>  $aliases
     */
    private function resolveKey(array $keys, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (in_array($alias, $keys, true)) {
                return $alias;
            }
        }

        return null;
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function displayValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
