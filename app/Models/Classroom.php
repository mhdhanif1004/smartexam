<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name'];

    /**
     * Cache jumlah kelas per grade level (di-reset per request).
     * dipakai summarizeTargets() supaya tidak query ulang tiap pemanggilan.
     */
    private static ?array $gradeCountsCache = null;

    /**
     * Ambil jumlah kelas per grade level dari seluruh DB.
     * Di-cache sekali per request via static property.
     */
    public static function getGradeCounts(): array
    {
        if (self::$gradeCountsCache !== null) {
            return self::$gradeCountsCache;
        }

        self::$gradeCountsCache = DB::table('classes')
            ->selectRaw("CASE WHEN name REGEXP '^[A-Z]+' THEN SUBSTRING_INDEX(name, ' ', 1) ELSE 'Lain' END AS grade, COUNT(*) AS cnt")
            ->groupBy('grade')
            ->pluck('cnt', 'grade')
            ->toArray();

        return self::$gradeCountsCache;
    }

    /**
     * Ringkas daftar classroom_id jadi list bagian (badge terstruktur).
     * Jika SEMUA kelas pada 1 tingkat tercakup → bagian bertipe 'grade'
     * (tingkat saja, misal "XI"). Kelas yang hanya sebagian tingkatnya
     * terpilih tetap tampil per kelas (tipe 'class').
     *
     * Contoh output (label):
     *   "X, XI"                         → X dan XI lengkap semua kelasnya
     *   "X, XI RPL 1, XI TKJ 1"        → X lengkap, tapi XI cuma sebagian
     *   "X AKL 1, X RPL 1, XI"         → X cuma sebagian, XI lengkap
     *   "X AKL 1, X AKL 2, XI RPL 1"   → tidak ada tingkat yang lengkap
     *
     * Tingkat lengkap diurutkan X → XI → XII; kelas individual tetap
     * mempertahankan urutan masuknya.
     *
     * @param  iterable<int>  $classroomIds
     * @return list<array{type: 'grade'|'class', label: string, count?: int}>
     */
    public static function summarizeTargetParts(iterable $classroomIds): array
    {
        $ids = is_array($classroomIds) ? $classroomIds : $classroomIds->all();
        if ($ids === []) {
            return [];
        }

        $allClassrooms = self::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'name']);

        // Map: grade_level → [classroom_ids]
        $gradeMap = [];
        foreach ($allClassrooms as $c) {
            preg_match('/^[A-Z]+/iu', $c->name, $m);
            $level = $m[0] ?? 'Lain';
            $gradeMap[$level][] = $c->id;
        }

        $allCounts = self::getGradeCounts();

        $fullGradeParts = [];
        $remaining = [];

        foreach ($gradeMap as $level => $gradeIds) {
            $totalInDb = $allCounts[$level] ?? count($gradeIds);
            if (count($gradeIds) >= $totalInDb) {
                $fullGradeParts[] = ['type' => 'grade', 'label' => $level, 'count' => $totalInDb];
            } else {
                foreach ($gradeIds as $id) {
                    $remaining[] = ['type' => 'class', 'label' => (string) $allClassrooms->firstWhere('id', $id)->name];
                }
            }
        }

        // Sort tingkat: X → XI → XII → Lain
        $levelOrder = ['X' => 10, 'XI' => 11, 'XII' => 12];
        usort($fullGradeParts, fn (array $a, array $b) => ($levelOrder[$a['label']] ?? 99) <=> ($levelOrder[$b['label']] ?? 99));

        return array_merge($fullGradeParts, $remaining);
    }

    /**
     * Ringkas daftar classroom_id jadi label teks singkat (dipakai konteks
     * teks biasa, mis. ringkasan kelompok soal admin). Hasil akhir sama
     * dengan gabungan label dari summarizeTargetParts().
     *
     * @param  iterable<int>  $classroomIds
     */
    public static function summarizeTargets(iterable $classroomIds): string
    {
        return implode(', ', array_map(
            fn (array $part): string => $part['label'],
            self::summarizeTargetParts($classroomIds)
        ));
    }

    /**
     * Ambil id kelas dari nama; buat otomatis bila belum ada di master data.
     * Dipakai semua jalur penulisan siswa agar class_name dan classroom_id
     * selalu sinkron.
     */
    public static function idForName(string $name): int
    {
        return self::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'classroom_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_classroom')->withTimestamps();
    }

    public function guruMapels(): BelongsToMany
    {
        return $this->belongsToMany(
            GuruMapel::class,
            'teacher_subject_class_assignments',
            'classroom_id',
            'guru_mapel_id'
        )->withTimestamps();
    }

    public function waliKelas(): HasOne
    {
        return $this->hasOne(WaliKelas::class, 'classroom_id');
    }
}
