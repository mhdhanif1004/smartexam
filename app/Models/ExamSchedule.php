<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ExamScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'exam_period_id',
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

    /**
     * Status yang dihitung REAL-TIME berdasarkan waktu sekarang (Carbon::now())
     * dibandingkan dengan exam_date + start_time dan exam_date + end_time,
     * BUKAN dari kolom status yang tersimpan statis di database.
     */
    public function computedStatus(): string
    {
        $now = Carbon::now();

        if ($now->lt($this->examStart())) {
            return self::STATUS_SCHEDULED;
        }

        if ($now->lt($this->examEnd())) {
            return self::STATUS_ONGOING;
        }

        return self::STATUS_FINISHED;
    }

    /**
     * Jendela absensi pengawas: terbuka 10 menit sebelum ujian dimulai dan
     * menutup saat waktu selesai (inklusif), sesuai exam_date + start_time/end_time.
     */
    public function isAttendanceWindowOpen(): bool
    {
        return $this->windowOpen(10);
    }

    /**
     * Jendela token ujian: tersedia 5 menit sebelum ujian dimulai dan
     * menutup saat waktu selesai (inklusif).
     */
    public function isTokenWindowOpen(): bool
    {
        return $this->windowOpen(5);
    }

    /**
     * Apakah waktu sekarang berada dalam jendela [start_time - earlyMinutes, end_time].
     */
    public function windowOpen(int $earlyMinutes = 0): bool
    {
        $now = Carbon::now();

        return $now->gte($this->windowOpensAt($earlyMinutes)) && $now->lte($this->examEnd());
    }

    /**
     * Datetime penuh mulai ujian = exam_date + start_time.
     */
    public function examStart(): Carbon
    {
        return Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->start_time);
    }

    /**
     * Datetime penuh selesai ujian = exam_date + end_time.
     */
    public function examEnd(): Carbon
    {
        return Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->end_time);
    }

    /**
     * Datetime jendela dibuka = exam_date + start_time - earlyMinutes.
     */
    public function windowOpensAt(int $earlyMinutes = 0): Carbon
    {
        return $this->examStart()->subMinutes($earlyMinutes);
    }

    public function getCurrentStatusAttribute(): string
    {
        return $this->computedStatus();
    }

    public function isStatusOutdated(): bool
    {
        return $this->status !== $this->computedStatus();
    }

    public function syncStatusIfNeeded(): void
    {
        $current = $this->computedStatus();

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
                    $schedule->updateQuietly(['status' => $schedule->computedStatus()]);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    /**
     * Filter jadwal berdasarkan computedStatus() real-time di level database,
     * tanpa bergantung pada kolom status statis.
     */
    public function scopeWhereComputedStatus(Builder $query, string $status): Builder
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        return match ($status) {
            self::STATUS_SCHEDULED => $query->where(function (Builder $q) use ($today, $time) {
                $q->whereDate('exam_date', '>', $today)
                    ->orWhere(function (Builder $q) use ($today, $time) {
                        $q->whereDate('exam_date', $today)
                            ->whereTime('start_time', '>', $time);
                    });
            }),
            self::STATUS_ONGOING => $query->whereDate('exam_date', $today)
                ->whereTime('start_time', '<=', $time)
                ->whereTime('end_time', '>', $time),
            self::STATUS_FINISHED => $query->where(function (Builder $q) use ($today, $time) {
                $q->whereDate('exam_date', '<', $today)
                    ->orWhere(function (Builder $q) use ($today, $time) {
                        $q->whereDate('exam_date', $today)
                            ->whereTime('end_time', '<=', $time);
                    });
            }),
            default => $query,
        };
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
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
     * ID siswa peserta jadwal ini. Untuk jadwal periode (exam_period_id
     * terisi) diambil dari exam_room_assignments pada ruangan jadwal; untuk
     * jadwal lama tanpa periode diambil dari penempatan permanen
     * students.room_id sebagai fallback.
     *
     * @return array<int, int>
     */
    public function participantStudentIds(): array
    {
        if ($this->exam_period_id !== null) {
            return ExamRoomAssignment::query()
                ->where('exam_period_id', $this->exam_period_id)
                ->where('room_id', $this->room_id)
                ->pluck('student_id')
                ->all();
        }

        return Student::query()
            ->where('room_id', $this->room_id)
            ->pluck('id')
            ->all();
    }

    /**
     * Koleksi siswa peserta jadwal ini.
     *
     * @return Collection<int, Student>
     */
    public function participantStudents(): Collection
    {
        return Student::query()
            ->with('user')
            ->whereIn('id', $this->participantStudentIds())
            ->orderBy('nisn')
            ->get();
    }

    public function hasParticipant(int $studentId): bool
    {
        return in_array($studentId, $this->participantStudentIds(), true);
    }

    public function scopeForParticipant(Builder $query, int $studentId): Builder
    {
        return $query->where(function (Builder $query) use ($studentId) {
            $query->whereNull('exam_period_id')
                ->whereHas('room.students', fn ($students) => $students->whereKey($studentId))
                ->orWhere(function (Builder $query) use ($studentId) {
                    $query->whereNotNull('exam_period_id')
                        ->whereHas('examPeriod.roomAssignments', fn ($assignments) => $assignments->where('student_id', $studentId));
                });
        });
    }

    /**
     * Filter jadwal yang dapat diakses oleh siswa tertentu, dengan aturan yang
     * sama seperti Student::isAssignedToSchedule(): jadwal periode
     * (exam_period_id terisi) dicocokkan lewat exam_room_assignments
     * (exam_period_id + student_id + room_id), jadwal lama tanpa periode
     * memakai penempatan permanen students.room_id.
     */
    public function scopeAccessibleToStudent(Builder $query, Student $student): Builder
    {
        return $query->where(function (Builder $query) use ($student) {
            $query->whereNull('exam_period_id')
                ->where('room_id', $student->room_id)
                ->orWhere(function (Builder $query) use ($student) {
                    $query->whereNotNull('exam_period_id')
                        ->whereExists(function ($query) use ($student) {
                            $query->selectRaw('1')
                                ->from('exam_room_assignments')
                                ->whereColumn('exam_room_assignments.exam_period_id', 'exam_schedules.exam_period_id')
                                ->whereColumn('exam_room_assignments.room_id', 'exam_schedules.room_id')
                                ->where('exam_room_assignments.student_id', $student->id);
                        });
                });
        });
    }

    /**
     * Rentang waktu jadwal dalam menit sejak tengah malam (0-1439 dst).
     * Menggunakan duration_minutes sebagai sumber kebenaran, bukan kolom
     * end_time yang tersimpan, supaya konsisten dengan perhitungan start_time
     * + duration di controller. Jadwal yang melewati tengah malam (end <= start)
     * otomatis ditambah 1440 menit sehingga urutannya benar.
     *
     * @return array{0: int, 1: int}
     */
    public function timeWindowMinutes(): array
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) $this->start_time));
        $start = $hour * 60 + $minute;

        if ($this->duration_minutes !== null && (int) $this->duration_minutes > 0) {
            $end = $start + (int) $this->duration_minutes;
        } else {
            [$endHour, $endMinute] = array_map('intval', explode(':', (string) $this->end_time));
            $end = $endHour * 60 + $endMinute;
        }

        return [$start, $end <= $start ? $end + 1440 : $end];
    }

    /**
     * Cari jadwal lain di ruangan dan tanggal yang sama yang waktunya bentrok
     * dengan rentang [startMinutes, endMinutes). Mengembalikan jadwal pertama
     * yang bentrok, atau null bila aman. Memakai rumus interval standar:
     * overlap = start_baru < end_lama AND end_baru > start_lama.
     * Bila suatu saat ada status "batal", jadwal tersebut tidak dihitung.
     */
    public static function findConflicting(
        int $roomId,
        string $examDate,
        int $startMinutes,
        int $endMinutes,
        ?int $excludeId = null,
    ): ?ExamSchedule {
        return self::query()
            ->with('subject')
            ->where('room_id', $roomId)
            ->whereDate('exam_date', $examDate)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when(in_array('cancelled', array_keys(self::STATUSES), true), fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->get()
            ->first(function (ExamSchedule $existing) use ($startMinutes, $endMinutes) {
                [$existingStart, $existingEnd] = $existing->timeWindowMinutes();

                if ($startMinutes < $existingEnd && $endMinutes > $existingStart) {
                    return true;
                }

                // Bila jadwal lama melewati tengah malam, bagian 00:00–waktu
                // selesai jatuh pada hari berikutnya; cek bagian terbungkus itu.
                if ($existingEnd > 1440) {
                    $wrappedEnd = $existingEnd - 1440;

                    return $startMinutes < $wrappedEnd;
                }

                return false;
            });
    }

    /**
     * Label waktu mulai (H:i) untuk pesan konflik.
     */
    public function startLabel(): string
    {
        return Carbon::parse((string) $this->start_time)->format('H:i');
    }

    /**
     * Label waktu selesai (H:i) untuk pesan konflik, dihitung dari rentang
     * menit agar konsisten dengan durasi (termasuk saat melewati tengah malam).
     */
    public function endLabel(): string
    {
        $end = $this->timeWindowMinutes()[1] % 1440;

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }
}
