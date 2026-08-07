<?php

namespace App\Models;

use Database\Factories\SupervisorAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorAttendance extends Model
{
    /** @use HasFactory<SupervisorAttendanceFactory> */
    use HasFactory;

    public const STATUS_PRESENT = 'hadir';

    public const STATUS_ABSENT = 'tidak_hadir';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Hadir',
        self::STATUS_ABSENT => 'Tidak Hadir',
    ];

    protected $fillable = [
        'supervisor_id',
        'exam_schedule_id',
        'room_id',
        'status',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PRESENT);
    }
}
