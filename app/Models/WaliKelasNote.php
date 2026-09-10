<?php

namespace App\Models;

use Database\Factories\WaliKelasNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaliKelasNote extends Model
{
    /** @use HasFactory<WaliKelasNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'classroom_id',
        'wali_kelas_id',
        'semester_id',
        'tipe',
        'catatan',
    ];

    public const TIPE_OPTIONS = [
        'observasi' => 'Observasi',
        'pelanggaran' => 'Pelanggaran',
        'prestasi' => 'Prestasi',
        'lainnya' => 'Lainnya',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(WaliKelas::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Label tipe yang ramah ditampilkan.
     */
    public function tipeLabel(): string
    {
        return self::TIPE_OPTIONS[$this->tipe] ?? $this->tipe ?? '-';
    }
}
