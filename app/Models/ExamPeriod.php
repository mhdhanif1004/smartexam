<?php

namespace App\Models;

use Database\Factories\ExamPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamPeriod extends Model
{
    /** @use HasFactory<ExamPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'name_prefix',
        'grade_level',
        'session_number',
        'exam_date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    public function supervisorRoomAssignments(): HasMany
    {
        return $this->hasMany(SupervisorRoomAssignment::class);
    }

    /**
     * Extract grade level (X, XI, XII) from a class name like "X RPL 1".
     */
    public static function extractGradeLevel(string $className): ?string
    {
        $grade = strtoupper(trim(explode(' ', $className)[0]));

        return in_array($grade, ['X', 'XI', 'XII'], true) ? $grade : null;
    }
}
