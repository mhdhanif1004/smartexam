<?php

namespace App\Imports;

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

class SupervisorsImport implements ToCollection, WithEvents, WithHeadingRow
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

    private const NAME_ALIASES = ['nama', 'nama_lengkap', 'nama_pengawas', 'name', 'nama_guru', 'nama_pegawai'];

    private const EMAIL_ALIASES = ['email', 'email_pengawas', 'email_guru', 'email_pegawai'];

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

        $nameKey = $this->resolveKey($keys, self::NAME_ALIASES);
        $emailKey = $this->resolveKey($keys, self::EMAIL_ALIASES);

        if ($nameKey === null || $emailKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey, $emailKey);

            return;
        }

        $seenEmails = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $rowNumber = $index + 2;
            $errors = [];

            $name = $this->normalizeText($rowData[$nameKey] ?? null);
            $email = $this->normalizeEmail($rowData[$emailKey] ?? null);

            if ($name === '' && $email === '') {
                continue;
            }

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

            $existingUser = $email !== '' ? User::query()->where('email', $email)->first() : null;

            if ($existingUser !== null && ! $existingUser->supervisor) {
                $errors[] = "Email {$email} sudah terdaftar pada akun lain (bukan pengawas).";
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'name' => $name !== '' ? $name : $this->displayValue($rowData[$nameKey] ?? null),
                        'email' => $email !== '' ? $email : $this->displayValue($rowData[$emailKey] ?? null),
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $seenEmails[$email] = $rowNumber;

            $mode = $this->estimateMode($email);
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
     * Perkirakan mode untuk sebuah email: 'update' jika akun pengawasnya
     * sudah ada di DB.
     */
    public function estimateMode(string $email): string
    {
        return User::query()->where('email', $email)->whereHas('supervisor')->exists() ? 'update' : 'create';
    }

    /**
     * Simpan satu baris valid. Return true = create, false = update.
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
