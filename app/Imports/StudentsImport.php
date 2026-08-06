<?php

namespace App\Imports;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class StudentsImport implements ToCollection, WithEvents, WithHeadingRow
{
    public string $headerError = '';

    /**
     * Baris valid yang siap disimpan (mode: create|update).
     *
     * @var list<array{row:int, nisn:string, name:string, class_name:string, mode:string}>
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
     * Nama kelas yang muncul di file tapi belum terdaftar di master data.
     * Kelas-kelas ini akan otomatis dibuat saat impor dikonfirmasi, bukan
     * menolak baris yang menggunakannya.
     *
     * @var list<string>
     */
    public array $newClasses = [];

    private const NISN_ALIASES = ['nisn', 'no_induk', 'nomor_induk', 'student_id', 'no_peserta'];

    private const NAME_ALIASES = ['nama', 'nama_lengkap', 'nama_siswa', 'name', 'student_name', 'nama_murid'];

    private const CLASS_ALIASES = ['kelas', 'class', 'class_name', 'nama_kelas', 'rombel', 'group'];

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

        $nisnKey = $this->resolveKey($keys, self::NISN_ALIASES);
        $nameKey = $this->resolveKey($keys, self::NAME_ALIASES);
        $classKey = $this->resolveKey($keys, self::CLASS_ALIASES);

        if ($nisnKey === null || $nameKey === null || $classKey === null) {
            $this->headerError = $this->missingHeaderMessage($nisnKey, $nameKey, $classKey);

            return;
        }

        $seenNisns = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;
            $errors = [];

            $nisn = $this->normalizeNisn($rowData[$nisnKey] ?? null);
            $name = $this->normalizeText($rowData[$nameKey] ?? null);
            $className = $this->normalizeText($rowData[$classKey] ?? null);

            if ($nisn === '' && $name === '' && $className === '') {
                continue;
            }

            if ($nisn === '') {
                $errors[] = 'NISN wajib diisi.';
            } elseif (! preg_match('/^\d{10}$/', $nisn)) {
                $errors[] = 'NISN harus 10 digit angka.';
            } elseif (isset($seenNisns[$nisn])) {
                $errors[] = "NISN {$nisn} duplikat di dalam file (bentrok dengan baris {$seenNisns[$nisn]}).";
            }

            if ($name === '') {
                $errors[] = 'Nama wajib diisi.';
            }

            if ($className === '') {
                $errors[] = 'Kelas wajib diisi.';
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'nisn' => $nisn !== '' ? $nisn : $this->displayValue($rowData[$nisnKey] ?? null),
                        'name' => $name !== '' ? $name : $this->displayValue($rowData[$nameKey] ?? null),
                        'class_name' => $className !== '' ? $className : $this->displayValue($rowData[$classKey] ?? null),
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $seenNisns[$nisn] = $rowNumber;

            $mode = $this->estimateMode($nisn);
            $mode === 'create' ? $this->toCreate++ : $this->toUpdate++;

            $this->validRows[] = [
                'row' => $rowNumber,
                'nisn' => $nisn,
                'name' => $name,
                'class_name' => $className,
                'mode' => $mode,
            ];

            // Baris valid memakai kelas yang belum ada di master data, jadi
            // nama kelas itu dikumpulkan untuk dibuat otomatis nanti saat
            // impor dikonfirmasi (dide-duplikasi agar tidak dua kali).
            if (! Classroom::query()->where('name', $className)->exists()
                && ! in_array($className, $this->newClasses, true)) {
                $this->newClasses[] = $className;
            }
        }
    }

    /**
     * Perkirakan mode untuk sebuah NISN: 'update' jika sudah ada di DB.
     */
    public function estimateMode(string $nisn): string
    {
        return Student::query()->where('nisn', $nisn)->exists() ? 'update' : 'create';
    }

    /**
     * Simpan satu baris valid. Return true = create, false = update.
     */
    public function upsertRow(array $validRow): bool
    {
        return DB::transaction(function () use ($validRow) {
            $student = Student::query()->where('nisn', $validRow['nisn'])->first();

            if ($student) {
                // Mode update: jangan sentuh username/password/room_id.
                $student->update(['class_name' => $validRow['class_name']]);
                $student->user?->update(['name' => $validRow['name']]);

                return false;
            }

            // Mode create: kredensial acak dari generator yang sama dengan
            // form manual, siswa belum ditempatkan ke ruangan (room_id null).
            $generator = app(CredentialGenerator::class);
            $password = $generator->password();

            $user = User::create([
                'name' => $validRow['name'],
                'username' => $generator->username(),
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_PESERTA,
                'is_active' => true,
            ]);

            Student::create([
                'user_id' => $user->id,
                'nisn' => $validRow['nisn'],
                'class_name' => $validRow['class_name'],
                'room_id' => null,
            ]);

            return true;
        });
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
                if ($this->upsertRow($validRow)) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$validRow['row']}: {$e->getMessage()}";
            }
        }

        return $result;
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

        $nisnKey = $this->resolveKey($headers, self::NISN_ALIASES);
        $nameKey = $this->resolveKey($headers, self::NAME_ALIASES);
        $classKey = $this->resolveKey($headers, self::CLASS_ALIASES);

        if ($nisnKey === null || $nameKey === null || $classKey === null) {
            $this->headerError = $this->missingHeaderMessage($nisnKey, $nameKey, $classKey);
        }
    }

    private function missingHeaderMessage(?string $nisnKey, ?string $nameKey, ?string $classKey): string
    {
        $missing = [];

        if ($nisnKey === null) {
            $missing[] = 'NISN';
        }
        if ($nameKey === null) {
            $missing[] = 'Nama';
        }
        if ($classKey === null) {
            $missing[] = 'Kelas';
        }

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .'. Gunakan header sesuai template (NISN, Nama, Kelas).';
    }

    /**
     * Cari kolom yang tersedia untuk sekelompok alias.
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

    /**
     * Ubah NISN dari berbagai format Excel (ilmiah/desimal) jadi string 10 digit.
     */
    private function normalizeNisn(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (is_numeric($value) && stripos($value, 'e') !== false) {
            $value = (string) (int) round((float) $value);
        } elseif (is_numeric($value) && str_contains($value, '.')) {
            $value = (string) (int) (float) $value;
        }

        return preg_replace('/\D/', '', $value) ?? '';
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
