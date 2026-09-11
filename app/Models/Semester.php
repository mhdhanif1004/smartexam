<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Semester extends Model
{
    use HasFactory;

    /** Mapping jenis -> angka semester (untuk urutan tampilan). */
    public const JENIS_ANGKA = [
        'ganjil' => 1,
        'genap' => 2,
    ];

    protected $fillable = [
        'academic_year_id',
        'jenis',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Scope: hanya semester yang aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Ambil semester aktif saat ini.
     *
     * Fallback: semester terbaru (by tahun ajaran + jenis desc) jika tidak
     * ada yang `is_active = true` (log warning kalau ini terjadi).
     */
    public static function getActive(): ?self
    {
        $active = static::query()->active()->first();

        if ($active !== null) {
            return $active;
        }

        // Fallback: semester terbaru (by tahun ajaran + jenis desc).
        // Log warning HANYA kalau ada semester tapi tidak ada yang aktif
        // (indikasi salah konfigurasi). Tabel kosong = normal, diam saja.
        $newest = static::query()
            ->with('academicYear')
            ->orderByDesc(
                AcademicYear::query()
                    ->select('nama')
                    ->whereColumn('academic_years.id', 'semesters.academic_year_id')
            )
            ->orderByDesc('jenis')
            ->first();

        if ($newest !== null) {
            Log::warning('Tidak ada semester aktif (is_active=true). Menggunakan semester terbaru sebagai fallback.', ['semester_id' => $newest->id]);
        }

        return $newest;
    }

    /**
     * Nama gabungan tahun ajaran + semester, contoh: "2024/2025 - Semester Ganjil".
     *
     * Dipakai di semua dropdown/list semester (admin maupun wali kelas).
     * Sumber dari relasi academicYear, bukan kolom gabungan lama.
     */
    public function getNamaLengkapAttribute(): string
    {
        $tahunAjaran = $this->academicYear?->nama ?? '-';

        return "{$tahunAjaran} - Semester {$this->jenisLabel}";
    }

    /**
     * Label ramah tampilan untuk jenis semester: 'ganjil' -> 'Ganjil'.
     */
    public function getJenisLabelAttribute(): string
    {
        return $this->jenis === 'ganjil' ? 'Ganjil' : 'Genap';
    }
}
