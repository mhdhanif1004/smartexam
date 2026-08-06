<?php

namespace App\Traits;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Violation;
use Illuminate\Support\Collection;

trait ScopesSupervisorRoom
{
    /**
     * Ruangan yang menjadi tanggung jawab pengawas yang sedang login.
     */
    protected function supervisorRoom(): Room
    {
        $room = auth()->user()?->supervisor?->room;

        abort_unless($room instanceof Room, 403, 'Anda tidak ditugaskan pada ruangan ujian.');

        return $room;
    }

    /**
     * Sesi ujian yang sedang berlangsung di ruangan pengawas hari ini.
     *
     * @return Collection<int, ExamSchedule>
     */
    protected function ongoingSchedules(Room $room): Collection
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        return ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->whereIn('status', [ExamSchedule::STATUS_SCHEDULED, ExamSchedule::STATUS_ONGOING])
            ->where('exam_date', '>=', $today)
            ->where('exam_date', '<', $tomorrow)
            ->orderBy('start_time')
            ->get()
            ->filter(fn (ExamSchedule $schedule) => $schedule->status === ExamSchedule::STATUS_ONGOING
                || ($now->format('H:i:s') >= $schedule->start_time && $now->format('H:i:s') <= $schedule->end_time))
            ->values();
    }

    /**
     * Sesi yang sedang berjalan untuk halaman absensi/token (dengan pilihan lewat query param).
     */
    protected function currentSchedule(Room $room, ?int $requestedId = null): ?ExamSchedule
    {
        $schedules = $this->ongoingSchedules($room);

        if ($schedules->isEmpty()) {
            return null;
        }

        if ($requestedId !== null) {
            return $schedules->firstWhere('id', $requestedId);
        }

        return $schedules->first();
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
