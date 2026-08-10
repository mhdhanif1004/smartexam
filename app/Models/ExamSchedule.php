<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ExamScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ExamSchedule extends Model
{
    /** @use HasFactory<ExamScheduleFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_FINISHED = 'finished';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Terjadwal',
        self::STATUS_ONGOING => 'Berlangsung',
        self::STATUS_FINISHED => 'Selesai',
    ];

    protected $fillable = [
        'subject_id',
        'room_id',
        'class_name',
        'exam_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    protected $appends = ['current_status'];

    public function getCurrentStatusAttribute(): string
    {
        $now = now();
        $examDateTime = Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->start_time);
        $endDateTime = $examDateTime->copy()->addMinutes($this->duration_minutes);

        if ($now->lt($examDateTime)) {
            return self::STATUS_SCHEDULED;
        }

        if ($now->gte($examDateTime) && $now->lt($endDateTime)) {
            return self::STATUS_ONGOING;
        }

        return self::STATUS_FINISHED;
    }

    public function isStatusOutdated(): bool
    {
        return $this->status !== $this->current_status;
    }

    public function syncStatusIfNeeded(): void
    {
        $current = $this->current_status;

        if ($this->status !== $current) {
            $this->updateQuietly(['status' => $current]);
        }
    }

    public static function syncAllStatuses(): int
    {
        $updated = 0;

        self::chunkById(100, function ($schedules) use (&$updated) {
            foreach ($schedules as $schedule) {
                if ($schedule->isStatusOutdated()) {
                    $schedule->updateQuietly(['status' => $schedule->current_status]);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function examTokens(): HasMany
    {
        return $this->hasMany(ExamToken::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * Peserta ujian jadwal ini = seluruh siswa yang ditempatkan permanen di
     * ruangan tempat jadwal diselenggarakan (students.room_id).
     */
    public function participantStudents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            Room::class,
            'id',
            'room_id',
            'room_id',
            'id'
        );
    }

    public function hasParticipant(int $studentId): bool
    {
        return $this->participantStudents()->whereKey($studentId)->exists();
    }

    public function scopeForParticipant(Builder $query, int $studentId): Builder
    {
        return $query->whereHas('room.students', fn ($students) => $students->whereKey($studentId));
    }
}
