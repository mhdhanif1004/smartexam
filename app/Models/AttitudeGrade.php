<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttitudeGrade extends Model
{
    protected $fillable = [
        'student_id',
        'classroom_id',
        'wali_kelas_id',
        'attitude_aspect_id',
        'semester_id',
        'score',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

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

    public function attitudeAspect(): BelongsTo
    {
        return $this->belongsTo(AttitudeAspect::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
