<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_NOT_STARTED => 'Belum Mulai',
        self::STATUS_IN_PROGRESS => 'Sedang Mengerjakan',
        self::STATUS_COMPLETED => 'Selesai',
    ];

    public const ATTENDANCE_PRESENT = 'hadir';

    public const ATTENDANCE_ABSENT = 'tidak_hadir';

    public const ATTENDANCE_STATUSES = [
        self::ATTENDANCE_PRESENT => 'Hadir',
        self::ATTENDANCE_ABSENT => 'Tidak Hadir',
    ];

    public const DEADLINE_TYPE_SCHEDULE_END = 'schedule_end';

    public const DEADLINE_TYPE_DURATION = 'duration';

    protected $fillable = [
        'student_id',
        'exam_schedule_id',
        'started_at',
        'deadline_type',
        'finished_at',
        'status',
        'attendance_status',
        'attendance_confirmed',
        'attendance_confirmed_at',
        'attendance_confirmed_by',
        'violation_flag_1',
        'violation_flag_2',
        'violation_flag_3',
        'locked_by_admin',
        'locked_by_admin_at',
        'locked_by_admin_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attendance_confirmed' => 'boolean',
            'attendance_confirmed_at' => 'datetime',
            'violation_flag_1' => 'boolean',
            'violation_flag_2' => 'boolean',
            'violation_flag_3' => 'boolean',
            'locked_by_admin' => 'boolean',
            'locked_by_admin_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function examAnswers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    public function examResult(): HasOne
    {
        return $this->hasOne(ExamResult::class);
    }

    public function scopeForStudentAndSchedule(Builder $query, int $studentId, int $scheduleId): Builder
    {
        return $query->where('student_id', $studentId)->where('exam_schedule_id', $scheduleId);
    }

    public function activeViolationFlags(): int
    {
        return (int) $this->violation_flag_1
            + (int) $this->violation_flag_2
            + (int) $this->violation_flag_3;
    }

    /**
     * Hitung deadline mapel berdasarkan deadline_type yang di-snapshot.
     * NULL diperlakukan sebagai 'duration' (fallback untuk data lama).
     */
    public function deadline(?ExamSchedule $schedule = null): Carbon
    {
        $schedule ??= $this->examSchedule;

        $type = $this->deadline_type ?? self::DEADLINE_TYPE_DURATION;

        return $type === self::DEADLINE_TYPE_SCHEDULE_END
            ? $schedule->examEnd()
            : $this->started_at->copy()->addMinutes((int) $schedule->duration_minutes);
    }

    /**
     * Aktifkan slot checklist pelanggaran berikutnya yang masih kosong.
     * Maksimal tiga slot; bila semuanya sudah aktif tidak ada yang diubah.
     */
    public function activateNextViolationFlag(): bool
    {
        foreach (['violation_flag_1', 'violation_flag_2', 'violation_flag_3'] as $flag) {
            if (! $this->{$flag}) {
                $this->update([$flag => true]);

                return true;
            }
        }

        return false;
    }
}
