<?php

namespace App\Models;

use Database\Factories\SupervisorRoomAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorRoomAssignment extends Model
{
    /** @use HasFactory<SupervisorRoomAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_period_id',
        'exam_date',
        'supervisor_id',
        'room_id',
        'rotation_index',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
