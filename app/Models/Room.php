<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'room_number',
        'capacity',
        'supervisor_count',
    ];

    protected function casts(): array
    {
        return [
            'room_number' => 'integer',
            'supervisor_count' => 'integer',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return 'Ruang '.$this->room_number;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(Supervisor::class);
    }

    public function examSchedules(): HasMany
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

    public function roomOverrides(): HasMany
    {
        return $this->hasMany(ExamPeriodRoomOverride::class);
    }
}
