<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = [
        'guru_mapel_id',
        'subject_id',
        'classroom_id',
        'student_id',
        'semester_id',
        'score',
        'note',
        'is_override',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_override' => 'boolean',
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
