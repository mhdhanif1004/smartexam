<?php

namespace App\Models;

use Database\Factories\SupervisorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supervisor extends Model
{
    /** @use HasFactory<SupervisorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'supervisor_room_assignments')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SupervisorAttendance::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(SupervisorRoomAssignment::class);
    }
}
