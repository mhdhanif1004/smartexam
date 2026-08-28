<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    public const TYPE_TUGAS = 'tugas';

    public const TYPE_HARIAN = 'harian';

    public const TYPE_UTS = 'uts';

    public const TYPE_UAS = 'uas';

    public const TYPE_LAINNYA = 'lainnya';

    public const TYPES = [
        self::TYPE_TUGAS => 'Tugas',
        self::TYPE_HARIAN => 'Ulangan Harian',
        self::TYPE_UTS => 'UTS',
        self::TYPE_UAS => 'UAS',
        self::TYPE_LAINNYA => 'Lainnya',
    ];

    protected $fillable = [
        'guru_mapel_id',
        'subject_id',
        'classroom_id',
        'student_id',
        'grade_type',
        'title',
        'score',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
