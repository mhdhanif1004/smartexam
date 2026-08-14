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
}
