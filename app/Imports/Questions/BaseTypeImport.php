<?php

namespace App\Imports\Questions;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

abstract class BaseTypeImport implements ToCollection, WithEvents, WithHeadingRow, WithStartRow
{
    public string $headerError = '';

    /**
     * Judul sheet yang sedang diproses (diisi event BeforeSheet).
     */
    protected string $currentSheetTitle = '';

    /**
     * Apakah sheet data yang sesuai nama template pernah ditemukan.
     */
    protected bool $dataSheetSeen = false;

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

    /**
     * Kelas target yang dipilih di modal impor (UI). Satu-satunya sumber
     * target kelas untuk import; berlaku untuk SEMUA baris soal pada sesi
     * impor ini. Kolom "Kelas Target" di file Excel tidak lagi dibaca.
     *
     * @var list<int>
     */
    public array $applyClassroomIds = [];

    abstract public function type(): string;

    /**
     * Judul sheet data yang dibaca. Dipakai untuk memastikan baris hanya
     * diproses dari sheet yang tepat (referensi eksplisit by nama, bukan
     * index sheet) sehingga aman bila workbook punya sheet tambahan lain.
     */
    abstract public function dataSheetName(): string;

    abstract protected function typeLabel(): string;

    /**
     * Peta kolom => daftar alias header (slug) yang diterima.
     *
     * @return array<string, list<string>>
     */
    abstract protected function aliases(): array;

    /**
     * @return list<string>
     */
    abstract protected function requiredKeys(): array;

    /**
     * Proses satu baris data.
     *
     * @param  array<string, mixed>  $rowData
     * @param  array<string, ?string>  $resolved
     * @return array<string, mixed>|null null = baris kosong (dilewati)
     */
    abstract protected function handleRow(array $rowData, array $resolved): ?array;

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Data mulai dibaca dari baris 2. Baris contoh pada template (teks
     * diawali "CONTOH:") dilewati otomatis berdasarkan isinya, sehingga
     * file buatan sendiri tanpa baris contoh tetap terbaca semua barisnya.
     */
    public function startRow(): int
    {
        return 2;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                $this->currentSheetTitle = '';
                $this->dataSheetSeen = false;
                $this->headerError = '';
            },
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->getSheet()->getDelegate();
                $this->currentSheetTitle = $sheet->getTitle();

                if (! $this->isDataSheet($this->currentSheetTitle)) {
                    return;
                }

                $this->dataSheetSeen = true;
                $this->detectHeaders($sheet);
            },
            AfterImport::class => function () {
                if (! $this->dataSheetSeen && $this->headerError === '') {
                    $this->headerError = 'Sheet data "'.$this->dataSheetName().'" tidak ditemukan di file.'
                        .' Pastikan file adalah template impor soal '.$this->typeLabel().' yang benar.';
                }
            },
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($this->headerError !== '' || ! $this->isDataSheet($this->currentSheetTitle)) {
            return;
        }

        $keys = $rows->isNotEmpty() ? $rows->first()->keys()->all() : [];
        $resolved = [];

        foreach ($this->aliases() as $key => $aliases) {
            $resolved[$key] = $this->resolveKey($keys, $aliases);
        }

        foreach ($this->requiredKeys() as $key) {
            if ($resolved[$key] === null) {
                $this->headerError = $this->missingHeaderMessage($this->requiredKeys());

                return;
            }
        }

        $startRow = $this->startRow();

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();

            // Lewati baris contoh template (judul dimulai "CONTOH:").
            if (isset($resolved['question']) && $resolved['question'] !== null) {
                $questionText = $this->normalizeText($rowData[$resolved['question']] ?? null);
                if (Str::startsWith(Str::upper($questionText), 'CONTOH')) {
                    continue;
                }
            }

            $result = $this->handleRow($rowData, $resolved);

            if ($result === null) {
                continue;
            }

            $rowNumber = $index + $startRow;

            if (! empty($result['errors'])) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'subject' => $result['subject'] ?? '-',
                        'type' => $this->typeLabel(),
                        'question_text' => ($result['question_text'] ?? '') !== '' ? $result['question_text'] : '-',
                    ],
                    'errors' => $result['errors'],
                ];

                continue;
            }

            $this->toCreate++;
            $this->validRows[] = [
                'row' => $rowNumber,
                ...$result['payload'],
            ];
        }
    }

    /**
     * Simpan semua baris valid sekaligus.
     *
     * @return array{created:int, updated:int, errors:list<string>}
     */
    public function persistRows(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($this->validRows as $validRow) {
            try {
                $question = Question::create([
                    'subject_id' => $validRow['subject_id'],
                    'type' => $validRow['type'],
                    'question_text' => $validRow['question_text'],
                    'options' => $validRow['options'],
                    'answer_key' => $validRow['answer_key'],
                    'score_weight' => $validRow['score_weight'],
                    'is_active' => true,
                ]);

                $targetIds = array_values(array_unique($this->applyClassroomIds));

                if (! empty($targetIds)) {
                    $question->classrooms()->sync($targetIds);
                }

                $result['created']++;
            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$validRow['row']}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    protected function resolveSubject(string $name): ?Subject
    {
        if ($name === '') {
            return null;
        }

        return Subject::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * Sheet yang boleh diproses: sheet data sesuai nama template, atau
     * "Worksheet" untuk file CSV (selalu satu sheet dengan nama tersebut).
     */
    protected function isDataSheet(string $title): bool
    {
        return $title === $this->dataSheetName() || $title === 'Worksheet';
    }

    protected function parseWeight(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 10.0;
        }

        return (float) trim((string) $value);
    }

    /**
     * @param  mixed  $value
     */
    protected function parseBoolean(string $value, ?bool &$parsed): bool
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
    protected function splitLines(string $value): array
    {
        return collect(preg_split('/[\r\n]+/', trim($value)))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    protected function normalizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    protected function displayValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }

    /**
     * Baca header baris pertama file agar kolom wajib yang hilang bisa
     * dilaporkan walau file tidak memiliki baris data sama sekali.
     */
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

        foreach ($this->requiredKeys() as $key) {
            if ($this->resolveKey($headers, $this->aliases()[$key]) === null) {
                $this->headerError = $this->missingHeaderMessage($this->requiredKeys());

                return;
            }
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function missingHeaderMessage(array $keys): string
    {
        $labels = [
            'subject' => 'Mata Pelajaran',
            'question' => 'Pertanyaan',
        ];

        $missing = collect($keys)
            ->map(fn (string $key) => $labels[$key] ?? ucwords(str_replace('_', ' ', $key)))
            ->values()
            ->all();

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .". Gunakan template impor soal {$this->typeLabel()} sebagai acuan.";
    }

    /**
     * @param  array<mixed>  $keys
     * @param  list<string>  $aliases
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
}
