<?php

namespace App\Imports;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class StudentsImport implements ToCollection, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
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

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    private const NISN_ALIASES = ['nisn', 'no_induk', 'nomor_induk', 'student_id', 'no_peserta'];

    private const NAME_ALIASES = ['nama', 'nama_lengkap', 'nama_siswa', 'name', 'student_name', 'nama_murid'];

    private const CLASS_ALIASES = ['kelas', 'class', 'class_name', 'nama_kelas', 'rombel', 'group'];

    public function registerEvents(): array
    {
        return [];
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

        // First pass: collect all valid NISNs and class names from this chunk
        $chunkNisns = [];
        $chunkClassNames = [];
        $rowDataList = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;

            $nisn = $this->normalizeNisn($rowData[$nisnKey] ?? null);
            $name = $this->normalizeText($rowData[$nameKey] ?? null);
            $className = $this->normalizeText($rowData[$classKey] ?? null);

            if ($nisn === '' && $name === '' && $className === '') {
                continue;
            }

            $rowDataList[] = [
                'rowNumber' => $rowNumber,
                'nisn' => $nisn,
                'name' => $name,
                'className' => $className,
                'rawNisn' => $this->displayValue($rowData[$nisnKey] ?? null),
                'rawName' => $this->displayValue($rowData[$nameKey] ?? null),
                'rawClassName' => $this->displayValue($rowData[$classKey] ?? null),
            ];

            if ($nisn !== '') {
                $chunkNisns[] = $nisn;
            }
            if ($className !== '') {
                $chunkClassNames[] = $className;
            }
        }

        if (empty($rowDataList)) {
            return;
        }

        // Batch query: get existing NISNs in DB (single query)
        $existingNisns = Student::query()
            ->whereIn('nisn', $chunkNisns)
            ->pluck('nisn')
            ->flip()
            ->toArray();

        // Batch query: get existing class names in DB (single query)
        $existingClasses = Classroom::query()
            ->whereIn('name', $chunkClassNames)
            ->pluck('name')
            ->flip()
            ->toArray();

        $seenNisns = [];

        foreach ($rowDataList as $data) {
            $rowNumber = $data['rowNumber'];
            $nisn = $data['nisn'];
            $name = $data['name'];
            $className = $data['className'];
            $errors = [];

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
                        'nisn' => $nisn !== '' ? $nisn : $data['rawNisn'],
                        'name' => $name !== '' ? $name : $data['rawName'],
                        'class_name' => $className !== '' ? $className : $data['rawClassName'],
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $seenNisns[$nisn] = $rowNumber;

            // Check if NISN exists in DB (from batch query result)
            $mode = isset($existingNisns[$nisn]) ? 'update' : 'create';
            $mode === 'create' ? $this->toCreate++ : $this->toUpdate++;

            $this->validRows[] = [
                'row' => $rowNumber,
                'nisn' => $nisn,
                'name' => $name,
                'class_name' => $className,
                'mode' => $mode,
            ];

            // Collect new class names (not in DB, not already in newClasses)
            if (! isset($existingClasses[$className]) && ! in_array($className, $this->newClasses, true)) {
                $this->newClasses[] = $className;
            }
        }
    }

    /**
     * Simpan semua baris valid sekaligus menggunakan batch operations.
     *
     * @return array{created:int, updated:int, errors:list<string>}
     */
    public function persistRows(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        if (empty($this->validRows)) {
            return $result;
        }

        // Separate create and update rows
        $createRows = [];
        $updateRows = [];

        foreach ($this->validRows as $validRow) {
            if ($validRow['mode'] === 'create') {
                $createRows[] = $validRow;
            } else {
                $updateRows[] = $validRow;
            }
        }

        // Batch create: create users first, then students
        if (! empty($createRows)) {
            try {
                $created = $this->batchCreate($createRows);
                $result['created'] += $created;
            } catch (Throwable $e) {
                // Fallback to individual processing for error tracking
                foreach ($createRows as $validRow) {
                    try {
                        if ($this->upsertRow($validRow)) {
                            $result['created']++;
                        }
                    } catch (Throwable $ex) {
                        $result['errors'][] = "Baris {$validRow['row']}: {$ex->getMessage()}";
                    }
                }
            }
        }

        // Batch update
        if (! empty($updateRows)) {
            try {
                $updated = $this->batchUpdate($updateRows);
                $result['updated'] += $updated;
            } catch (Throwable $e) {
                // Fallback to individual processing for error tracking
                foreach ($updateRows as $validRow) {
                    try {
                        if (! $this->upsertRow($validRow)) {
                            $result['updated']++;
                        }
                    } catch (Throwable $ex) {
                        $result['errors'][] = "Baris {$validRow['row']}: {$ex->getMessage()}";
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Batch create new students with users.
     */
    private function batchCreate(array $createRows): int
    {
        return DB::transaction(function () use ($createRows) {
            $generator = app(CredentialGenerator::class);

            // Create users in batch
            $usersData = [];
            foreach ($createRows as $row) {
                $password = $generator->password();
                $username = $generator->username();
                $usersData[] = [
                    'name' => $row['name'],
                    'username' => $username,
                    'password' => Hash::make($password),
                    'plain_password' => Crypt::encryptString($password),
                    'role' => User::ROLE_PESERTA,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert users and get IDs
            DB::table('users')->insert($usersData);

            // Get the inserted user IDs
            $usernames = array_column($usersData, 'username');
            $users = User::query()
                ->whereIn('username', $usernames)
                ->orderBy('id')
                ->get(['id', 'username'])
                ->keyBy('username');

            // Create students in batch
            $studentsData = [];
            foreach ($createRows as $index => $row) {
                $username = $usersData[$index]['username'];
                $userId = $users[$username]->id ?? null;

                if ($userId) {
                    $studentsData[] = [
                        'user_id' => $userId,
                        'nisn' => $row['nisn'],
                        'class_name' => $row['class_name'],
                        'classroom_id' => Classroom::idForName($row['class_name']),
                        'room_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($studentsData)) {
                DB::table('students')->insert($studentsData);
            }

            return count($studentsData);
        });
    }

    /**
     * Batch update existing students.
     */
    private function batchUpdate(array $updateRows): int
    {
        return DB::transaction(function () use ($updateRows) {
            $nisns = array_column($updateRows, 'nisn');

            // Get existing students with user_id
            $students = Student::query()
                ->whereIn('nisn', $nisns)
                ->get(['id', 'nisn', 'user_id'])
                ->keyBy('nisn');

            // Batch update students
            $studentsUpdates = [];
            foreach ($updateRows as $row) {
                $student = $students[$row['nisn']] ?? null;
                if ($student) {
                    $studentsUpdates[] = [
                        'id' => $student->id,
                        'class_name' => $row['class_name'],
                        'classroom_id' => Classroom::idForName($row['class_name']),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($studentsUpdates)) {
                Student::upsert($studentsUpdates, ['id'], ['class_name', 'classroom_id', 'updated_at']);
            }

            // Batch update users
            $userUpdates = [];
            foreach ($updateRows as $row) {
                $student = $students[$row['nisn']] ?? null;
                if ($student && $student->user_id) {
                    $userUpdates[] = [
                        'id' => $student->user_id,
                        'name' => $row['name'],
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($userUpdates)) {
                User::upsert($userUpdates, ['id'], ['name', 'updated_at']);
            }

            return count($studentsUpdates);
        });
    }

    /**
     * Simpan satu baris valid (fallback untuk error handling per baris).
     * Return true = create, false = update.
     */
    public function upsertRow(array $validRow): bool
    {
        return DB::transaction(function () use ($validRow) {
            $student = Student::query()->where('nisn', $validRow['nisn'])->first();

            if ($student) {
                // Mode update: jangan sentuh username/password/room_id.
                $student->update([
                    'class_name' => $validRow['class_name'],
                    'classroom_id' => Classroom::idForName($validRow['class_name']),
                ]);
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
                'classroom_id' => Classroom::idForName($validRow['class_name']),
                'room_id' => null,
            ]);

            return true;
        });
    }

    /**
     * Perkirakan mode untuk sebuah NISN: 'update' jika sudah ada di DB.
     * (Ditahan untuk kompatibilitas, tapi sekarang dipakai batch query di collection())
     */
    public function estimateMode(string $nisn): string
    {
        return Student::query()->where('nisn', $nisn)->exists() ? 'update' : 'create';
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
