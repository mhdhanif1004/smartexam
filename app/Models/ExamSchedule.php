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
use Illuminate\Validation\ValidationException;

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
        'classroom_id',
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
     * Pertahanan level model: tepat SATU dari room_id atau classroom_id
     * harus terisi — exactly one. Melengkapi validasi di Form Request
     * + CHECK constraint di database sebagai defense-in-depth.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            $hasRoom = $model->room_id !== null;
            $hasClassroom = $model->classroom_id !== null;

            if (! ($hasRoom ^ $hasClassroom)) {
                throw ValidationException::withMessages([
                    'room_id' => ['Sisi ujian harus memiliki salah satu: Ruangan atau Kelas — tidak boleh keduanya maupun kosong.'],
                ]);
            }
        });
    }

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
     * Toleransi absensi ulang: jendela absensi pengawas tetap terbuka sampai
     * N menit setelah waktu ujian selesai. Nilai diambil dari config
     * exam.attendance_tolerance_minutes (default 10), bukan di-hardcode.
     */
    public static function attendanceToleranceMinutes(): int
    {
        return (int) config('exam.attendance_tolerance_minutes', 10);
    }

    /**
     * Jendela absensi pengawas: terbuka 10 menit sebelum ujian dimulai dan
     * menutup tolerance menit SETELAH waktu selesai (inklusif), supaya
     * pengawas tetap bisa melakukan absensi ulang selama jeda antar sesi.
     */
    public function isAttendanceWindowOpen(): bool
    {
        return $this->windowOpen(10, self::attendanceToleranceMinutes());
    }

    /**
     * Jendela token ujian: tersedia 5 menit sebelum ujian dimulai dan
     * menutup saat waktu selesai (inklusif). Token TIDAK diberi toleransi.
     */
    public function isTokenWindowOpen(): bool
    {
        return $this->windowOpen(5);
    }

    /**
     * Waktu jendela absensi ditutup total = end_time + tolerance menit.
     */
    public function attendanceWindowClosesAt(): Carbon
    {
        return $this->examEnd()->addMinutes(self::attendanceToleranceMinutes());
    }

    /**
     * Apakah waktu sekarang berada dalam jendela
     * [start_time - earlyMinutes, end_time + lateMinutes].
     */
    public function windowOpen(int $earlyMinutes = 0, int $lateMinutes = 0): bool
    {
        $now = Carbon::now();

        return $now->gte($this->windowOpensAt($earlyMinutes)) && $now->lte($this->examEnd()->addMinutes($lateMinutes));
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
     * Check if the session has expired based on the sesi (period) deadline
     * including grace period. For schedules without an ExamPeriod, falls
     * back to the per-mapel deadline via ExamSession::deadline().
     *
     * Single source of truth for expiry logic used by saveAnswer(),
     * toggleDoubtful(), submit(), and ViolationController::store().
     */
    public function isExpiredAfterGrace(ExamSession $session): bool
    {
        $period = $this->examPeriod;

        if ($period !== null) {
            $periodEnd = Carbon::parse(
                $period->exam_date->format('Y-m-d').' '.$period->end_time
            );
            $graceMinutes = config('exam.grace_period_minutes', 10);
            $sesiDeadline = $periodEnd->copy()->addMinutes($graceMinutes);

            return now()->gt($sesiDeadline);
        }

        return now()->gt($session->deadline($this));
    }

    /**
     * Datetime jendela dibuka = exam_date + start_time - earlyMinutes.
     */
    public function windowOpensAt(int $earlyMinutes = 0): Carbon
    {
        return $this->examStart()->subMinutes($earlyMinutes);
    }

    /**
     * Apakah siswa masih boleh mengerjakan mapel ini karena sesi
     * (ExamPeriod) keseluruhan belum berakhir? Dipakai untuk menentukan
     * status "susulan" di dashboard siswa.
     */
    public function isWithinPeriodWindow(): bool
    {
        $period = $this->examPeriod;

        if ($period === null) {
            return $this->computedStatus() === self::STATUS_ONGOING;
        }

        $periodEnd = Carbon::parse(
            $period->exam_date->format('Y-m-d').' '.$period->end_time
        );

        return now()->lte($periodEnd);
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

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
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
        if ($this->room_id === null) {
            return $this->classroom_id !== null
                ? Student::query()->where('classroom_id', $this->classroom_id)->pluck('id')->all()
                : [];
        }

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
     * ID siswa peserta untuk BANYAK jadwal sekaligus, dalam 1-2 query agregat
     * (bukan 1 query per jadwal). Hasil berupa Collection keyed by schedule id
     * => array<int, int>. Dipakai di halaman absensi/dashboard pengawas yang
     * sebelumnya memanggil participantStudentIds() per jadwal di dalam loop
     * (N+1).
     *
     * @param  Collection<int, ExamSchedule>  $schedules
     * @return Collection<int, array<int, int>>
     */
    public static function participantStudentIdsBySchedules(Collection $schedules): Collection
    {
        $result = $schedules->mapWithKeys(fn (self $schedule) => [$schedule->id => []]);

        if ($schedules->isEmpty()) {
            return $result;
        }

        $periodRoomSchedules = $schedules->filter(fn (self $s) => $s->exam_period_id !== null && $s->room_id !== null);
        $legacyRoomSchedules = $schedules->filter(fn (self $s) => $s->exam_period_id === null && $s->room_id !== null);
        $classroomSchedules = $schedules->filter(fn (self $s) => $s->room_id === null && $s->classroom_id !== null);

        if ($periodRoomSchedules->isNotEmpty()) {
            $assignments = ExamRoomAssignment::query()
                ->whereIn('exam_period_id', $periodRoomSchedules->pluck('exam_period_id')->unique()->values())
                ->whereIn('room_id', $periodRoomSchedules->pluck('room_id')->unique()->values())
                ->get(['exam_period_id', 'room_id', 'student_id'])
                ->groupBy(fn ($assignment) => $assignment->exam_period_id.'|'.$assignment->room_id);

            foreach ($periodRoomSchedules as $schedule) {
                $result[$schedule->id] = $assignments
                    ->get($schedule->exam_period_id.'|'.$schedule->room_id, collect())
                    ->pluck('student_id')
                    ->values()
                    ->all();
            }
        }

        if ($legacyRoomSchedules->isNotEmpty()) {
            $studentsByRoom = Student::query()
                ->whereIn('room_id', $legacyRoomSchedules->pluck('room_id')->unique()->values())
                ->get(['room_id', 'id'])
                ->groupBy('room_id');

            foreach ($legacyRoomSchedules as $schedule) {
                $result[$schedule->id] = $studentsByRoom
                    ->get($schedule->room_id, collect())
                    ->pluck('id')
                    ->values()
                    ->all();
            }
        }

        if ($classroomSchedules->isNotEmpty()) {
            $studentsByClassroom = Student::query()
                ->whereIn('classroom_id', $classroomSchedules->pluck('classroom_id')->unique()->values())
                ->get(['classroom_id', 'id'])
                ->groupBy('classroom_id');

            foreach ($classroomSchedules as $schedule) {
                $result[$schedule->id] = $studentsByClassroom
                    ->get($schedule->classroom_id, collect())
                    ->pluck('id')
                    ->values()
                    ->all();
            }
        }

        return $result;
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
        $studentClassroomId = Student::whereKey($studentId)->value('classroom_id');

        return $query->where(function (Builder $query) use ($studentId, $studentClassroomId) {
            $query->whereNull('exam_period_id')
                ->whereNull('classroom_id')
                ->whereHas('room.students', fn ($students) => $students->whereKey($studentId))
                ->orWhere(function (Builder $query) use ($studentId) {
                    $query->whereNotNull('exam_period_id')
                        ->whereNull('classroom_id')
                        ->whereHas('examPeriod.roomAssignments', fn ($assignments) => $assignments->where('student_id', $studentId));
                })
                ->orWhere(function (Builder $query) use ($studentClassroomId) {
                    $query->whereNull('room_id')
                        ->where('classroom_id', $studentClassroomId);
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
            // Legacy room-only (no period, no classroom)
            $query->whereNull('exam_period_id')
                ->whereNull('classroom_id')
                ->where('room_id', $student->room_id)
                // Period + room (admin schedule assignment)
                ->orWhere(function (Builder $query) use ($student) {
                    $query->whereNotNull('exam_period_id')
                        ->whereNull('classroom_id')
                        ->whereExists(function ($query) use ($student) {
                            $query->selectRaw('1')
                                ->from('exam_room_assignments')
                                ->whereColumn('exam_room_assignments.exam_period_id', 'exam_schedules.exam_period_id')
                                ->whereColumn('exam_room_assignments.room_id', 'exam_schedules.room_id')
                                ->where('exam_room_assignments.student_id', $student->id);
                        });
                })
                // Classroom-based (guru ujian)
                ->orWhere(function (Builder $query) use ($student) {
                    $query->whereNull('room_id')
                        ->where('classroom_id', $student->classroom_id);
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
        ?int $roomId,
        string $examDate,
        int $startMinutes,
        int $endMinutes,
        ?int $excludeId = null,
        ?int $classroomId = null,
    ): ?ExamSchedule {
        $query = self::query()
            ->with('subject')
            ->whereDate('exam_date', $examDate);

        if ($classroomId !== null) {
            $query->whereNull('room_id')
                ->where('classroom_id', $classroomId);
        } elseif ($roomId !== null) {
            $query->where('room_id', $roomId);
        } else {
            return null;
        }

        return $query
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when(in_array('cancelled', array_keys(self::STATUSES), true), fn ($query) => $query->where('status', '!=', 'cancelled'))
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
