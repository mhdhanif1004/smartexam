<?php

namespace App\Imports;

use App\Models\Supervisor;
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
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class SupervisorsImport implements ToCollection, WithBatchInserts, WithChunkReading, WithCustomCsvSettings, WithEvents, WithHeadingRow
{
    public string $headerError = '';

    /**
     * Impor pengawas hanya berbentuk satu kolom Nama. Memaksa delimiter
     * koma agar CSV satu kolom dengan spasi di dalam nama (mis. "Andi
     * Pratama") tidak terpotong oleh auto-detect delimiter bawaan.
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
        ];
    }

    /**
     * Baris valid yang siap disimpan.
     *
     * Struktur entry:
     * - dup=false  -> nama belum ada, mode selalu 'create'.
     * - dup=true   -> nama sudah cocok dengan satu pengawas; mode default
     *   'update' (perbarui pengawas yang cocok), admin bisa memilih 'create'
     *   (tambahkan duplikat baru) per baris di langkah konfirmasi.
     * Ketika mode='update', existing_supervisor_id menunjuk pengawas sasaran.
     *
     * @var list<array{row:int, name:string, mode:string, dup:bool, existing_supervisor_id:?int, existing_name:?string}>
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

        if ($nameKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey);

            return;
        }

        // First pass: collect all names from this chunk
        $chunkNames = [];
        $rowDataList = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;

            $name = $this->normalizeText($rowData[$nameKey] ?? null);

            if ($name === '') {
                continue;
            }

            $rowDataList[] = [
                'rowNumber' => $rowNumber,
                'name' => $name,
                'rawName' => $this->displayValue($rowData[$nameKey] ?? null),
            ];

            $chunkNames[] = $name;
        }

        if (empty($rowDataList)) {
            return;
        }

        // Batch query: supervisor users whose name matches any row in this chunk.
        // name => list of [supervisor_id, user_name] (a name can match >1 record).
        $matched = User::query()
            ->whereIn('name', $chunkNames)
            ->whereHas('supervisor')
            ->with('supervisor:id,user_id')
            ->get(['id', 'name'])
            ->groupBy('name')
            ->map(function ($users) {
                return $users->map(fn ($u) => [
                    'supervisor_id' => $u->supervisor->id,
                    'user_name' => $u->name,
                ])->all();
            })
            ->toArray();

        $seenNames = [];

        foreach ($rowDataList as $data) {
            $rowNumber = $data['rowNumber'];
            $name = $data['name'];
            $errors = [];

            if ($name === '') {
                $errors[] = 'Nama wajib diisi.';
            } elseif (isset($seenNames[$name])) {
                $errors[] = "Nama {$name} duplikat di dalam file (bentrok dengan baris {$seenNames[$name]}).";
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => ['name' => $data['rawName']],
                    'errors' => $errors,
                ];

                continue;
            }

            $seenNames[$name] = $rowNumber;

            $candidates = $matched[$name] ?? [];

            // Duplicate hanya ketika nama cocok dengan TEPAT satu pengawas
            // (hanya dalam hal itu mode 'update' tidak ambigu). Bila cocok
            // dengan banyak pengawas, selalu buat baru.
            $dup = count($candidates) === 1;

            $mode = $dup ? 'update' : 'create';
            $mode === 'create' ? $this->toCreate++ : $this->toUpdate++;

            $this->validRows[] = [
                'row' => $rowNumber,
                'name' => $name,
                'mode' => $mode,
                'dup' => $dup,
                'existing_supervisor_id' => $dup ? $candidates[0]['supervisor_id'] : null,
                'existing_name' => $dup ? $candidates[0]['user_name'] : null,
            ];
        }
    }

    /**
     * Daftar baris duplikat (nama sudah cocok dengan satu pengawas) untuk
     * ditampilkan ke admin di langkah konfirmasi, lengkap dengan mode default.
     *
     * @return list<array{row:int, name:string, existing_supervisor_id:int, existing_name:string, mode:string}>
     */
    public function duplicates(): array
    {
        $list = [];

        foreach ($this->validRows as $validRow) {
            if (! $validRow['dup']) {
                continue;
            }

            $list[] = [
                'row' => $validRow['row'],
                'name' => $validRow['name'],
                'existing_supervisor_id' => $validRow['existing_supervisor_id'],
                'existing_name' => $validRow['existing_name'],
                'mode' => $validRow['mode'],
            ];
        }

        return $list;
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
     * Batch create new supervisors with users. Username di-generate acak unik
     * (sama seperti peserta) agar pengawas bisa login dengan username.
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
                    'username' => $generator->username(),
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
            $usernames = array_column($usersData, 'username');
            $users = User::query()
                ->whereIn('username', $usernames)
                ->orderBy('id')
                ->get(['id', 'username'])
                ->keyBy('username');

            // Create supervisor records in batch
            $supervisorsData = [];
            foreach ($createRows as $index => $row) {
                $username = $usersData[$index]['username'];
                $userId = $users[$username]->id ?? null;

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
     * Batch update existing supervisors (dinamai dari existing_supervisor_id).
     */
    private function batchUpdate(array $updateRows): int
    {
        return DB::transaction(function () use ($updateRows) {
            $supervisorIds = array_column($updateRows, 'existing_supervisor_id');

            // Get existing supervisors with user_id
            $supervisors = Supervisor::query()
                ->whereIn('id', $supervisorIds)
                ->with('user:id,name')
                ->get(['id', 'user_id'])
                ->keyBy('id');

            // Batch update users (name only)
            $userUpdates = [];
            foreach ($updateRows as $row) {
                $supervisor = $supervisors[$row['existing_supervisor_id']] ?? null;
                if ($supervisor?->user_id) {
                    $userUpdates[] = [
                        'id' => $supervisor->user_id,
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
            if ($validRow['mode'] === 'update' && ! empty($validRow['existing_supervisor_id'])) {
                // Mode update: jangan sentuh password, username, maupun ruangan.
                $supervisor = Supervisor::query()->find($validRow['existing_supervisor_id']);

                if ($supervisor) {
                    $supervisor->user?->update(['name' => $validRow['name']]);

                    return false;
                }
            }

            // Mode create: kredensial acak dari generator yang sama dengan
            // form manual, pengawas belum ditempatkan ke ruangan (room_id null).
            $generator = app(CredentialGenerator::class);
            $password = $generator->password();

            $user = User::create([
                'name' => $validRow['name'],
                'username' => $generator->username(),
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

        if ($nameKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey);
        }
    }

    private function missingHeaderMessage(?string $nameKey): string
    {
        $missing = [];

        if ($nameKey === null) {
            $missing[] = 'Nama';
        }

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .'. Gunakan header sesuai template (Nama).';
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
