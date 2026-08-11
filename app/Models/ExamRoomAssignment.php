<?php

namespace App\Models;

use Database\Factories\ExamRoomAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRoomAssignment extends Model
{
    /** @use HasFactory<ExamRoomAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_period_id',
        'student_id',
        'room_id',
        'seat_number',
    ];

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
