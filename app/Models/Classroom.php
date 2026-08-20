<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Ringkas daftar classroom_id jadi label singkat.
     * Jika SEMUA kelas pada 1 tingkat tercakup → tampilkan tingkat saja (misal "XI").
     * Sisa kelas individual tetap ditampilkan nama per kelas.
     *
     * Contoh output:
     *   "X, XI"                         → X dan XI lengkap semua kelasnya
     *   "X, XI RPL 1, XI TKJ 1"        → X lengkap, tapi XI cuma sebagian
     *   "X AKL 1, X RPL 1, XI"         → X cuma sebagian, XI lengkap
     *   "X AKL 1, X AKL 2, XI RPL 1"   → tidak ada tingkat yang lengkap
     *
     * @param  iterable<int>  $classroomIds
     */
    public static function summarizeTargets(iterable $classroomIds): string
    {
        $ids = is_array($classroomIds) ? $classroomIds : $classroomIds->all();
        if ($ids === []) {
            return '';
        }

        $allClassrooms = self::query()->whereIn('id', $ids)->get(['id', 'name']);

        // Map: grade_level → [classroom_ids]
        $gradeMap = [];
        foreach ($allClassrooms as $c) {
            preg_match('/^[A-Z]+/iu', $c->name, $m);
            $level = $m[0] ?? 'Lain';
            $gradeMap[$level][] = $c->id;
        }

        $allCounts = self::getGradeCounts();

        $fullGrades = [];
        $remaining = [];

        foreach ($gradeMap as $level => $gradeIds) {
            $totalInDb = $allCounts[$level] ?? count($gradeIds);
            if (count($gradeIds) >= $totalInDb) {
                $fullGrades[] = $level;
            } else {
                foreach ($gradeIds as $id) {
                    $remaining[$id] = $allClassrooms->firstWhere('id', $id)->name;
                }
            }
        }

        // Sort tingkat: X → XI → XII → Lain
        $levelOrder = ['X' => 10, 'XI' => 11, 'XII' => 12];
        usort($fullGrades, fn (string $a, string $b) => ($levelOrder[$a] ?? 99) <=> ($levelOrder[$b] ?? 99));

        $parts = array_merge($fullGrades, array_values($remaining));

        return implode(', ', $parts);
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
}
