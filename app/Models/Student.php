<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nisn',
        'class_name',
        'classroom_id',
        'room_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    /**
     * Apakah siswa merupakan peserta jadwal ujian ini. Menggunakan scope
     * ExamSchedule::accessibleToStudent() sebagai satu-satunya sumber aturan:
     * jadwal periode (exam_period_id terisi) dicek dari exam_room_assignments
     * pada ruangan jadwal; jadwal lama tanpa periode memakai penempatan
     * permanen students.room_id sebagai fallback.
     */
    public function isAssignedToSchedule(ExamSchedule $schedule): bool
    {
        return ExamSchedule::query()
            ->whereKey($schedule->id)
            ->accessibleToStudent($this)
            ->exists();
    }
}
