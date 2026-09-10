<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectGrade extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CBT = 'cbt';

    protected $fillable = [
        'student_id',
        'classroom_id',
        'subject_id',
        'guru_mapel_id',
        'semester_id',
        'exam_type_id',
        'title',
        'exam_schedule_id',
        'score',
        'note',
        'source',
        'is_override',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_override' => 'boolean',
            'taken_at' => 'date',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}
