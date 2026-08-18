<?php

namespace App\Imports;

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

class SupervisorsImport implements ToCollection, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
{
    public string $headerError = '';

    /**
     * Baris valid yang siap disimpan (mode: create|update).
     *
     * @var list<array{row:int, name:string, email:string, mode:string}>
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

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    private const NAME_ALIASES = ['nama', 'nama_lengkap', 'nama_pengawas', 'name', 'nama_guru', 'nama_pegawai'];

    private const EMAIL_ALIASES = ['email', 'email_pengawas', 'email_guru', 'email_pegawai'];

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

        $nameKey = $this->resolveKey($keys, self::NAME_ALIASES);
        $emailKey = $this->resolveKey($keys, self::EMAIL_ALIASES);

        if ($nameKey === null || $emailKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey, $emailKey);

            return;
        }

        // First pass: collect all valid emails from this chunk
        $chunkEmails = [];
        $rowDataList = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;

            $name = $this->normalizeText($rowData[$nameKey] ?? null);
            $email = $this->normalizeEmail($rowData[$emailKey] ?? null);

            if ($name === '' && $email === '') {
                continue;
            }

            $rowDataList[] = [
                'rowNumber' => $rowNumber,
                'name' => $name,
                'email' => $email,
                'rawName' => $this->displayValue($rowData[$nameKey] ?? null),
                'rawEmail' => $this->displayValue($rowData[$emailKey] ?? null),
            ];

            if ($email !== '') {
                $chunkEmails[] = $email;
            }
        }

        if (empty($rowDataList)) {
            return;
        }

        // Batch query: get existing users with supervisor role (single query)
        $existingSupervisors = User::query()
            ->whereIn('email', $chunkEmails)
            ->whereHas('supervisor')
            ->pluck('email')
            ->flip()
            ->toArray();

        // Batch query: get all existing users (for checking if email used by other role)
        $existingUsers = User::query()
            ->whereIn('email', $chunkEmails)
            ->pluck('email', 'id')
            ->flip()
            ->toArray();

        $seenEmails = [];

        foreach ($rowDataList as $data) {
            $rowNumber = $data['rowNumber'];
            $name = $data['name'];
            $email = $data['email'];
            $errors = [];

            if ($name === '') {
                $errors[] = 'Nama wajib diisi.';
            }

            if ($email === '') {
                $errors[] = 'Email wajib diisi.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Email {$email} tidak valid.";
            } elseif (isset($seenEmails[$email])) {
                $errors[] = "Email {$email} duplikat di dalam file (bentrok dengan baris {$seenEmails[$email]}).";
            }

            // Check if email exists in DB but not as supervisor
            $hasSupervisor = isset($existingSupervisors[$email]);
            $hasOtherRole = isset($existingUsers[$email]) && ! $hasSupervisor;

            if ($hasOtherRole) {
                $errors[] = "Email {$email} sudah terdaftar pada akun lain (bukan pengawas).";
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'name' => $name !== '' ? $name : $data['rawName'],
                        'email' => $email !== '' ? $email : $data['rawEmail'],
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $seenEmails[$email] = $rowNumber;

            // Check if email exists as supervisor (from batch query result)
            $mode = $hasSupervisor ? 'update' : 'create';
            $mode === 'create' ? $this->toCreate++ : $this->toUpdate++;

            $this->validRows[] = [
                'row' => $rowNumber,
                'name' => $name,
                'email' => $email,
                'mode' => $mode,
            ];
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

        // Batch create
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
     * Batch create new supervisors with users.
     */
    private function batchCreate(array $createRows): int
    {
        return DB::transaction(function () use ($createRows) {
            $generator = app(CredentialGenerator::class);

            // Create users in batch
            $usersData = [];
            foreach ($createRows as $row) {
                $password = $generator->password();
                $usersData[] = [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'username' => null,
                    'password' => Hash::make($password),
                    'plain_password' => Crypt::encryptString($password),
                    'role' => User::ROLE_PENGAWAS,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert users
            DB::table('users')->insert($usersData);

            // Get the inserted user IDs
            $emails = array_column($usersData, 'email');
            $users = User::query()
                ->whereIn('email', $emails)
                ->orderBy('id')
                ->get(['id', 'email'])
                ->keyBy('email');

            // Create supervisor records in batch
            $supervisorsData = [];
            foreach ($createRows as $index => $row) {
                $email = $usersData[$index]['email'];
                $userId = $users[$email]->id ?? null;

                if ($userId) {
                    $supervisorsData[] = [
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($supervisorsData)) {
                DB::table('supervisors')->insert($supervisorsData);
            }

            return count($supervisorsData);
        });
    }

    /**
     * Batch update existing supervisors.
     */
    private function batchUpdate(array $updateRows): int
    {
        return DB::transaction(function () use ($updateRows) {
            $emails = array_column($updateRows, 'email');

            // Get existing supervisors with user_id
            $supervisors = User::query()
                ->whereIn('email', $emails)
                ->whereHas('supervisor')
                ->with('supervisor')
                ->get(['id', 'email'])
                ->keyBy('email');

            // Batch update users (name only)
            $userUpdates = [];
            foreach ($updateRows as $row) {
                $user = $supervisors[$row['email']] ?? null;
                if ($user) {
                    $userUpdates[] = [
                        'id' => $user->id,
                        'name' => $row['name'],
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($userUpdates)) {
                User::upsert($userUpdates, ['id'], ['name', 'updated_at']);
            }

            return count($userUpdates);
        });
    }

    /**
     * Simpan satu baris valid (fallback untuk error handling per baris).
     * Return true = create, false = update.
     */
    public function upsertRow(array $validRow): bool
    {
        return DB::transaction(function () use ($validRow) {
            $user = User::query()->where('email', $validRow['email'])->whereHas('supervisor')->first();

            if ($user) {
                // Mode update: jangan sentuh password maupun ruangan.
                $user->update(['name' => $validRow['name']]);

                return false;
            }

            // Mode create: password acak dari generator yang sama dengan
            // form manual, pengawas belum ditempatkan ke ruangan (room_id null).
            $generator = app(CredentialGenerator::class);
            $password = $generator->password();

            $user = User::create([
                'name' => $validRow['name'],
                'email' => $validRow['email'],
                'username' => null,
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_PENGAWAS,
                'is_active' => true,
            ]);

            $user->supervisor()->create();

            return true;
        });
    }

    /**
     * Perkirakan mode untuk sebuah email: 'update' jika akun pengawasnya
     * sudah ada di DB.
     * (Ditahan untuk kompatibilitas, tapi sekarang dipakai batch query di collection())
     */
    public function estimateMode(string $email): string
    {
        return User::query()->where('email', $email)->whereHas('supervisor')->exists() ? 'update' : 'create';
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

        $nameKey = $this->resolveKey($headers, self::NAME_ALIASES);
        $emailKey = $this->resolveKey($headers, self::EMAIL_ALIASES);

        if ($nameKey === null || $emailKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey, $emailKey);
        }
    }

    private function missingHeaderMessage(?string $nameKey, ?string $emailKey): string
    {
        $missing = [];

        if ($nameKey === null) {
            $missing[] = 'Nama';
        }
        if ($emailKey === null) {
            $missing[] = 'Email';
        }

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .'. Gunakan header sesuai template (Nama, Email).';
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

    private function normalizeEmail(mixed $value): string
    {
        return strtolower(trim((string) $value));
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
