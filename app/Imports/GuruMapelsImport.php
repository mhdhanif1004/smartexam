<?php

namespace App\Imports;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class GuruMapelsImport implements ToCollection, WithHeadingRow
{
    /**
     * Kolom Kelas berisi literal ini diartikan sebagai "seluruh kelas pada
     * tingkat bersangkutan" dan diperluas (snapshot) saat validasi.
     */
    public const SEMUA = 'SEMUA';

    public string $headerError = '';

    /**
     * Baris valid yang siap disimpan.
     *
     * @var list<array{
     *     row:int,
     *     nama:string,
     *     email:string,
     *     subject_id:int,
     *     classroom_ids:list<int>,
     * }>
     */
    public array $validRows = [];

    /**
     * Baris yang gagal validasi.
     *
     * @var list<array{row:int, data:array<string,mixed>, errors:list<string>}>
     */
    public array $invalidRows = [];

    private const NAME_ALIASES = ['nama', 'nama_guru', 'nama_mapel_guru', 'name'];

    private const MAPEL_ALIASES = ['mapel', 'mata_pelajaran', 'subject', 'nama_mapel'];

    private const TINGKAT_ALIASES = ['tingkat', 'tingkat_kelas', 'kelas_tingkat', 'level'];

    private const KELAS_ALIASES = ['kelas', 'nama_kelas', 'kelas_mapel'];

    /** @var list<string> */
    private const VALID_TINGKATS = ['X', 'XI', 'XII'];

    /**
     * Cache email per nama normal (lowercase+trim) — dipakai agar beberapa
     * baris untuk guru yang sama memakai satu email/akun yang sama.
     *
     * @var array<string, string>
     */
    private array $emailCache = [];

    /**
     * Email yang sudah dipakai di sesi impor ini (untuk deteksi bentrok angka
     * pemberi nomor otomatis antar nama yang menghasilkan lokal-email sama).
     *
     * @var array<string, true>
     */
    private array $usedEmails = [];

    /**
     * Cache seluruh kelas (id => name) untuk perluasan SEMUA & validasi kelas,
     * diisi sekali per impor.
     *
     * @var array<int, string>|null
     */
    private ?array $classrooms = null;

    /**
     * Cache mapel (nama uppercase => id) agar lookup per baris efisien.
     *
     * @var array<string, int>|null
     */
    private ?array $subjects = null;

    public function collection(Collection $rows): void
    {
        if ($this->headerError !== '') {
            return;
        }

        $keys = $rows->isNotEmpty() ? $rows->first()->keys()->all() : [];

        $nameKey = $this->resolveKey($keys, self::NAME_ALIASES);
        $mapelKey = $this->resolveKey($keys, self::MAPEL_ALIASES);
        $tingkatKey = $this->resolveKey($keys, self::TINGKAT_ALIASES);
        $kelasKey = $this->resolveKey($keys, self::KELAS_ALIASES);

        if ($nameKey === null || $mapelKey === null || $tingkatKey === null || $kelasKey === null) {
            $this->headerError = $this->missingHeaderMessage($nameKey, $mapelKey, $tingkatKey, $kelasKey);

            return;
        }

        $subjects = $this->subjectMap();
        $classrooms = $this->classroomMap();

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();

            $nama = $this->normalizeText($rowData[$nameKey] ?? null);
            $mapel = $this->normalizeText($rowData[$mapelKey] ?? null);
            $tingkat = $this->normalizeText($rowData[$tingkatKey] ?? null);
            $kelas = $this->normalizeText($rowData[$kelasKey] ?? null);

            // Baris kosong seluruhnya -> lewati.
            if ($nama === '' && $mapel === '' && $tingkat === '' && $kelas === '') {
                continue;
            }

            // Baris contoh pada template (nama diawali "contoh") -> lewati.
            if (Str::startsWith(Str::lower($nama), 'contoh')) {
                continue;
            }

            $rowNumber = $index + 2;
            $errors = [];

            if ($nama === '') {
                $errors[] = 'Nama wajib diisi.';
            }

            if ($mapel === '') {
                $errors[] = 'Mapel wajib diisi.';
            } elseif (! isset($subjects[Str::upper($mapel)])) {
                $errors[] = "Mapel '{$this->displayValue($mapel)}' tidak ditemukan di master data.";
            }

            if ($tingkat === '') {
                $errors[] = 'Tingkat wajib diisi.';
            } elseif (! in_array(Str::upper($tingkat), self::VALID_TINGKATS, true)) {
                $errors[] = "Tingkat '{$this->displayValue($tingkat)}' tidak valid (harus X, XI, atau XII).";
            } elseif ($kelas !== '' && $this->isSemua($kelas)) {
                // SEMUA: perluas ke seluruh kelas pada tingkat tsb (snapshot).
                $allForTingkat = $this->classroomIdsForTingkat(Str::upper($tingkat));
                if (empty($allForTingkat)) {
                    $errors[] = "Tidak ada kelas terdaftar untuk tingkat {$this->displayValue($tingkat)} (perluasan SEMUA).";
                }
            } elseif ($kelas === '') {
                $errors[] = 'Kelas wajib diisi.';
            } else {
                $classroomId = array_search($kelas, $classrooms, true);
                if ($classroomId === false) {
                    $errors[] = "Kelas '{$this->displayValue($kelas)}' tidak ditemukan di master data.";
                } else {
                    $classroomTingkat = $this->gradeOf($kelas);
                    if (Str::upper($tingkat) !== $classroomTingkat) {
                        $errors[] = "Kelas '{$this->displayValue($kelas)}' bukan kelas tingkat {$this->displayValue($tingkat)}.";
                    }
                }
            }

            if (! empty($errors)) {
                $this->invalidRows[] = [
                    'row' => $rowNumber,
                    'data' => [
                        'nama' => $nama !== '' ? $nama : $this->displayValue($nama),
                        'mapel' => $mapel !== '' ? $mapel : $this->displayValue($mapel),
                        'tingkat' => $tingkat !== '' ? Str::upper($tingkat) : $this->displayValue($tingkat),
                        'kelas' => $kelas !== '' ? $this->isSemua($kelas) ? self::SEMUA : $kelas : $this->displayValue($kelas),
                    ],
                    'errors' => $errors,
                ];

                continue;
            }

            $subjectId = $subjects[Str::upper($mapel)];
            $classroomIds = $this->expandedClassroomIds($kelas, Str::upper($tingkat), $classrooms);
            $email = $this->emailFor($nama);

            $this->validRows[] = [
                'row' => $rowNumber,
                'nama' => $nama,
                'email' => $email,
                'subject_id' => $subjectId,
                'classroom_ids' => $classroomIds,
            ];
        }
    }

    /**
     * Simpan semua baris valid. Guru (user+record) yang belum ada dibuat;
     * penugasan (guru,mapel,kelas) di-upsert sehingga impor ulang menambah
     * penugasan baru tanpa menghapus yang lama.
     *
     * @return array{created:int, updated:int, assignments:int, errors:list<string>}
     */
    public function persistRows(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'assignments' => 0, 'errors' => []];
        $generator = app(CredentialGenerator::class);

        foreach ($this->validRows as $validRow) {
            try {
                // Reuse akun guru bila email sudah tercatat (impor ulang).
                $user = User::query()->where('email', $validRow['email'])
                    ->where('role', User::ROLE_GURU_MAPEL)
                    ->first();

                if ($user) {
                    $result['updated']++;
                } else {
                    $password = $generator->password();

                    $user = User::create([
                        'name' => $validRow['nama'],
                        'email' => $validRow['email'],
                        'username' => null,
                        'password' => $password,
                        'plain_password' => $password,
                        'role' => User::ROLE_GURU_MAPEL,
                        'is_active' => true,
                    ]);

                    $result['created']++;
                }

                $guru = GuruMapel::query()->firstOrCreate(['user_id' => $user->id]);

                foreach ($validRow['classroom_ids'] as $classroomId) {
                    TeacherSubjectClassAssignment::firstOrCreate([
                        'guru_mapel_id' => $guru->id,
                        'subject_id' => $validRow['subject_id'],
                        'classroom_id' => $classroomId,
                    ]);

                    $result['assignments']++;
                }
            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$validRow['row']}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /**
     * Email unik dari nama (pola sama dengan tambah manual): lowercase,
     * buang spasi/karakter non-alphanumerik, @gmail.com, dan beri angka bila
     * bentrok. Di-cache per nama normal dalam satu sesi impor.
     */
    private function emailFor(string $nama): string
    {
        $key = mb_strtolower(trim($nama));

        if (isset($this->emailCache[$key])) {
            return $this->emailCache[$key];
        }

        $generator = app(CredentialGenerator::class);
        $localPart = $generator->emailLocalPart($nama) ?: 'guru';

        $candidate = $localPart.'@gmail.com';
        $suffix = 1;

        while (
            User::query()->where('email', $candidate)->exists()
            || isset($this->usedEmails[$candidate])
        ) {
            $suffix++;
            $candidate = $localPart.$suffix.'@gmail.com';
        }

        $this->emailCache[$key] = $candidate;
        $this->usedEmails[$candidate] = true;

        return $candidate;
    }

    /**
     * Dapatkan ID kelas hasil perluasan SEMUA atau kelas tunggal.
     *
     * @param  array<int, string>  $classrooms
     * @return list<int>
     */
    private function expandedClassroomIds(string $kelas, string $tingkat, array $classrooms): array
    {
        if ($this->isSemua($kelas)) {
            return $this->classroomIdsForTingkat($tingkat);
        }

        $ids = array_keys($classrooms, $kelas, true);

        return $ids !== [] ? [(int) $ids[0]] : [];
    }

    /**
     * @return list<int>
     */
    private function classroomIdsForTingkat(string $tingkat): array
    {
        $ids = [];

        foreach ($this->classroomMap() as $id => $name) {
            if ($this->gradeOf($name) === $tingkat) {
                $ids[] = $id;
            }
        }

        sort($ids);

        return $ids;
    }

    private function isSemua(string $kelas): bool
    {
        return Str::upper(trim($kelas)) === self::SEMUA;
    }

    /**
     * Tingkat (grade) sebuah nama kelas: awalan huruf kapital (X/XI/XII).
     */
    private function gradeOf(string $name): string
    {
        preg_match('/^[A-Z]+/iu', trim($name), $m);

        return isset($m[0]) ? Str::upper($m[0]) : '';
    }

    /**
     * @return array<string, int>
     */
    private function subjectMap(): array
    {
        if ($this->subjects === null) {
            $this->subjects = Subject::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [Str::upper(trim($name)) => $id])
                ->all();
        }

        return $this->subjects;
    }

    /**
     * @return array<int, string>
     */
    private function classroomMap(): array
    {
        if ($this->classrooms === null) {
            $this->classrooms = Classroom::query()->pluck('name', 'id')->all();
        }

        return $this->classrooms;
    }

    private function missingHeaderMessage(?string $nameKey, ?string $mapelKey, ?string $tingkatKey, ?string $kelasKey): string
    {
        $missing = [];

        if ($nameKey === null) {
            $missing[] = 'Nama';
        }
        if ($mapelKey === null) {
            $missing[] = 'Mapel';
        }
        if ($tingkatKey === null) {
            $missing[] = 'Tingkat';
        }
        if ($kelasKey === null) {
            $missing[] = 'Kelas';
        }

        return 'Kolom wajib tidak ditemukan di file: '.implode(', ', $missing)
            .'. Gunakan header sesuai template (Nama, Mapel, Tingkat, Kelas).';
    }

    /**
     * Cari kolom yang tersedia untuk sekelompok alias.
     *
     * @param  list<string>  $keys
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
