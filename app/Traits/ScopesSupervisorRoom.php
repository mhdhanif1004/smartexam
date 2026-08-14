<?php

namespace App\Traits;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Violation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait ScopesSupervisorRoom
{
    /**
     * Ruangan yang menjadi tanggung jawab pengawas yang sedang login pada
     * hari ini. Diambil dari penugasan rotasi (supervisor_room_assignments)
     * untuk tanggal hari ini; bila tidak ada (misal periode legacy / belum
     * di-generate), fallback ke relasi statis Supervisor->room yang lama.
     */
    protected function supervisorRoom(): Room
    {
        $supervisor = auth()->user()?->supervisor;

        abort_unless($supervisor instanceof Supervisor, 403, 'Anda tidak terdaftar sebagai pengawas.');

        $room = $this->roomAssignedOn(now()->toDateString(), $supervisor) ?? $supervisor->room;

        abort_unless($room instanceof Room, 403, 'Anda tidak ditugaskan pada ruangan ujian.');

        return $room;
    }

    /**
     * Ruangan yang ditugaskan untuk pengawas pada tanggal tertentu melalui
     * rotasi. Karena satu pengawas bisa punya beberapa periode pada tanggal
     * yang sama (misal beberapa gelombang), ruangan yang aktif saat ini
     * diprioritaskan, lalu ruangan periode terawal sebagai fallback.
     */
    private function roomAssignedOn(string $date, Supervisor $supervisor): ?Room
    {
        $assignments = $supervisor->roomAssignments()
            ->with(['room', 'examPeriod'])
            ->where('exam_date', $date)
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $now = now();

        $active = $assignments->first(fn ($assignment) => $assignment->examPeriod !== null
            && $now->between(
                Carbon::parse($assignment->examPeriod->exam_date->format('Y-m-d').' '.$assignment->examPeriod->start_time),
                Carbon::parse($assignment->examPeriod->exam_date->format('Y-m-d').' '.$assignment->examPeriod->end_time),
            ));

        if ($active !== null) {
            return $active->room;
        }

        return $assignments
            ->sortBy(fn ($assignment) => $assignment->examPeriod?->start_time ?? '99:99:99')
            ->first()
            ->room;
    }

    /**
     * Sesi ujian hari ini di ruangan pengawas yang sedang berada dalam jendela
     * waktu tertentu (jendela absensi/token). Filter murni berbasis WAKTU
     * real-time via ExamSchedule::windowOpen(), bukan kolom status statis.
     *
     * @return Collection<int, ExamSchedule>
     */
    protected function windowSchedules(Room $room, int $earlyMinutes, int $lateMinutes = 0): Collection
    {
        $today = now()->startOfDay();

        return ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->where('exam_date', '>=', $today)
            ->where('exam_date', '<', $today->copy()->addDay())
            ->orderBy('start_time')
            ->get()
            ->filter(fn (ExamSchedule $schedule) => $schedule->windowOpen($earlyMinutes, $lateMinutes))
            ->values();
    }

    /**
     * Sesi yang sedang dalam jendela untuk halaman absensi/token
     * (dengan pilihan lewat query param).
     */
    protected function currentSchedule(Room $room, ?int $requestedId = null, int $earlyMinutes = 0, int $lateMinutes = 0): ?ExamSchedule
    {
        $schedules = $this->windowSchedules($room, $earlyMinutes, $lateMinutes);

        if ($schedules->isEmpty()) {
            return null;
        }

        if ($requestedId !== null) {
            return $schedules->firstWhere('id', $requestedId);
        }

        return $schedules->first();
    }

    /**
     * Sesi ujian hari ini di ruangan pengawas yang BELUM masuk jendela
     * (masih berstatus scheduled real-time). Dipakai untuk menampilkan
     * informasi "akan aktif mulai pukul HH:MM" kepada pengawas.
     *
     * @return Collection<int, ExamSchedule>
     */
    protected function upcomingSchedules(Room $room, int $earlyMinutes, int $lateMinutes = 0): Collection
    {
        $today = now()->startOfDay();

        return ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->where('exam_date', '>=', $today)
            ->where('exam_date', '<', $today->copy()->addDay())
            ->orderBy('start_time')
            ->get()
            ->filter(fn (ExamSchedule $schedule) => $schedule->computedStatus() === ExamSchedule::STATUS_SCHEDULED
                && ! $schedule->windowOpen($earlyMinutes, $lateMinutes))
            ->map(function (ExamSchedule $schedule) use ($earlyMinutes) {
                $schedule->setAttribute('window_start', $schedule->windowOpensAt($earlyMinutes)->format('H:i'));

                return $schedule;
            })
            ->values();
    }

    /**
     * Daftar peserta (dari penempatan tetap students.room_id di ruangan tempat
     * jadwal diselenggarakan) beserta sesi ujiannya untuk jadwal tertentu.
     *
     * @return Collection<int, Student>
     */
    protected function participants(ExamSchedule $schedule): Collection
    {
        return Student::query()
            ->with(['user', 'examSessions' => fn ($query) => $query->where('exam_schedule_id', $schedule->id)])
            ->where('room_id', $schedule->room_id)
            ->orderBy('nisn')
            ->get();
    }

    /**
     * Statistik status ujian peserta dari daftar peserta.
     *
     * @return array{belum_login: int, sedang_mengerjakan: int, selesai: int}
     */
    protected function participantStats(Collection $students): array
    {
        $stats = ['belum_login' => 0, 'sedang_mengerjakan' => 0, 'selesai' => 0];

        foreach ($students as $student) {
            $status = $student->examSessions->first()?->status ?? ExamSession::STATUS_NOT_STARTED;

            $stats[match ($status) {
                ExamSession::STATUS_IN_PROGRESS => 'sedang_mengerjakan',
                ExamSession::STATUS_COMPLETED => 'selesai',
                default => 'belum_login',
            }]++;
        }

        return $stats;
    }

    /**
     * Pelanggaran terbaru yang terjadi pada ruangan pengawas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function roomViolations(Room $room, int $limit = 5): Collection
    {
        return Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject'])
            ->whereHas('examSession.examSchedule', fn ($query) => $query->where('room_id', $room->id))
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (Violation $violation) => [
                'id' => $violation->id,
                'session_id' => $violation->exam_session_id,
                'student_name' => $violation->examSession?->student?->user?->name ?? '-',
                'class_name' => $violation->examSession?->student?->class_name ?? '-',
                'subject' => $violation->examSession?->examSchedule?->subject?->name ?? '-',
                'violation_type' => $violation->violation_type,
                'violation_label' => Violation::typeLabel($violation->violation_type),
                'occurred_at' => $violation->occurred_at?->format('d M H:i'),
                'flags' => [
                    (int) $violation->examSession?->violation_flag_1,
                    (int) $violation->examSession?->violation_flag_2,
                    (int) $violation->examSession?->violation_flag_3,
                ],
                'flag_count' => $violation->examSession?->activeViolationFlags() ?? 0,
                'handled' => (bool) $violation->handled_by_supervisor,
            ]);
    }
}
