<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Traits\ScopesSupervisorRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use ScopesSupervisorRoom;

    public function index(Request $request): View
    {
        $room = $this->supervisorRoom();
        $tolerance = ExamSchedule::attendanceToleranceMinutes();
        $periodIds = $this->assignedPeriodIds($room);

        // Active schedules for banner display (window-filtered)
        $schedules = $this->windowSchedules($room, 10, $tolerance, $periodIds);

        // ALL schedules in assigned periods — no window filter.
        // Student list spans every mapel so propagation reaches them all.
        $allSchedules = $this->allAssignedSchedules($room, $periodIds);

        $allStudentIds = $allSchedules
            ->map(fn (ExamSchedule $s) => $s->participantStudentIds())
            ->flatten()
            ->unique()
            ->values();

        $students = $allSchedules->isNotEmpty()
            ? $this->consolidatedAttendanceRows($allSchedules, $allStudentIds)
            : collect();

        // Anchor: first window-filtered schedule by start_time (deterministic for confirm AJAX).
        // Uses $schedules (window-filtered) NOT $allSchedules so the page only shows
        // the banner+table when an active or tolerance-window schedule exists.
        $anchorSchedule = $schedules->first();

        $upcomingSchedules = $this->upcomingSchedules($room, 10, $tolerance, $periodIds);

        return view('pengawas.attendance.index', compact(
            'room', 'schedules', 'allSchedules', 'anchorSchedule',
            'students', 'upcomingSchedules',
        ));
    }

    /**
     * Perbarui kehadiran satu siswa lewat AJAX (PATCH).
     */
    public function confirm(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $schedule->syncStatusIfNeeded();

        $room = $this->supervisorRoom();
        $periodIds = $this->assignedPeriodIds($room);

        if ($schedule->exam_period_id !== null && ! $periodIds->contains($schedule->exam_period_id)) {
            return response()->json(['error' => 'Anda tidak ditugaskan pada periode ujian jadwal ini.'], 403);
        }

        $ongoing = $this->currentSchedule($room, $schedule->id, 10, ExamSchedule::attendanceToleranceMinutes(), $periodIds);

        if ($ongoing === null) {
            return response()->json(['error' => 'Jadwal ujian tidak sedang dalam jendela absensi di ruangan Anda.'], 404);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'confirmed' => ['required', 'boolean'],
        ]);

        $student = Student::find($validated['student_id']);

        if ($student === null || ! $student->isAssignedToSchedule($schedule)) {
            return response()->json(['error' => 'Siswa bukan peserta pada sesi ujian ini.'], 422);
        }

        $session = ExamSession::query()->firstOrCreate(
            ['student_id' => $student->id, 'exam_schedule_id' => $schedule->id],
            ['status' => ExamSession::STATUS_NOT_STARTED],
        );

        if ($session->locked_by_admin) {
            return response()->json(['error' => 'Siswa ini dikunci oleh Admin.'], 423);
        }

        $confirmed = filter_var($validated['confirmed'], FILTER_VALIDATE_BOOLEAN);

        $session->update([
            'attendance_confirmed' => $confirmed,
            'attendance_confirmed_at' => now(),
            'attendance_confirmed_by' => auth()->id(),
            'attendance_status' => $confirmed ? ExamSession::ATTENDANCE_PRESENT : ExamSession::ATTENDANCE_ABSENT,
        ]);

        if ($confirmed) {
            $this->propagateAttendance($student, $schedule, true);
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request): RedirectResponse
    {
        $room = $this->supervisorRoom();
        $periodIds = $this->assignedPeriodIds($room);

        $allSchedules = $this->allAssignedSchedules($room, $periodIds);

        abort_if($allSchedules->isEmpty(), 404, 'Tidak ada sesi ujian yang sedang berlangsung di ruangan Anda.');

        $anchorSchedule = $allSchedules->first();

        if ($anchorSchedule->exam_period_id !== null && ! $periodIds->contains($anchorSchedule->exam_period_id)) {
            abort(403, 'Anda tidak ditugaskan pada periode ujian jadwal ini.');
        }

        $anchorSchedule->syncStatusIfNeeded();

        $allParticipantIds = $allSchedules
            ->map(fn (ExamSchedule $s) => $s->participantStudentIds())
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $validator = Validator::make($request->all(), [
            'attendance' => ['required', 'array'],
            'attendance.*' => ['required', Rule::in(array_keys(ExamSession::ATTENDANCE_STATUSES))],
        ]);

        $validator->after(function ($validator) use ($request, $allParticipantIds) {
            foreach (array_keys($request->input('attendance', [])) as $studentId) {
                if (! in_array((int) $studentId, $allParticipantIds, true)) {
                    $validator->errors()->add('attendance', 'Siswa bukan peserta pada sesi ujian ini.');

                    return;
                }
            }
        })->validate();

        $confirmedStatuses = [];

        foreach ($request->input('attendance') as $studentId => $status) {
            $confirmed = $status === ExamSession::ATTENDANCE_PRESENT;

            ExamSession::updateOrCreate(
                ['student_id' => $studentId, 'exam_schedule_id' => $anchorSchedule->id],
                [
                    'attendance_status' => $status,
                    'attendance_confirmed' => $confirmed,
                    'attendance_confirmed_at' => now(),
                    'attendance_confirmed_by' => auth()->id(),
                ]
            );

            if ($confirmed) {
                $confirmedStatuses[] = $studentId;
            }
        }

        if ($confirmedStatuses !== []) {
            $students = Student::whereIn('id', $confirmedStatuses)->get();
            foreach ($students as $student) {
                $this->propagateAttendance($student, $anchorSchedule, true);
            }
        }

        return back()->with('success', 'Absensi peserta berhasil disimpan.');
    }

    /**
     * ALL schedules in assigned periods for today — no window filter.
     * Used for consolidated attendance so propagation reaches every mapel.
     *
     * @return Collection<int, ExamSchedule>
     */
    private function allAssignedSchedules(Room $room, Collection $periodIds): Collection
    {
        $today = now()->startOfDay();

        $query = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->where('exam_date', '>=', $today)
            ->where('exam_date', '<', $today->copy()->addDay());

        if ($periodIds->isNotEmpty()) {
            $query->whereIn('exam_period_id', $periodIds);
        }

        return $query
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Read-only consolidated attendance rows. Does NOT create sessions or
     * run propagation — that happens at confirm() time via PUSH.
     *
     * @return Collection<int, Student>
     */
    private function consolidatedAttendanceRows(Collection $allSchedules, Collection $studentIds): Collection
    {
        $students = Student::query()
            ->with('user')
            ->whereIn('id', $studentIds)
            ->orderBy('nisn')
            ->get();

        $sessions = ExamSession::query()
            ->whereIn('exam_schedule_id', $allSchedules->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->withCount('violations')
            ->get()
            ->groupBy('student_id');

        foreach ($students as $student) {
            $studentSessions = $sessions->get($student->id, collect());
            $session = $studentSessions->first();
            $student->setRelation('examSession', $session);
        }

        return $students;
    }

    /**
     * Propagate attendance dalam satu ExamPeriod / ruangan.
     *
     * - overwriteExisting = true (dari confirm/update): schedule saat ini
     *   sudah di-confirm → PUSH ke semua schedule lain (create/update).
     * - overwriteExisting = false (dari attendanceRows): session baru
     *   dibuat untuk schedule ini → PULL dari schedule lain yang sudah
     *   di-confirm.
     *
     * Hanya mengisi sesi yang BELUM di-attend supaya data manual
     * pengawas tidak ter-overwrite.
     */
    private function propagateAttendance(Student $student, ExamSchedule $currentSchedule, bool $overwriteExisting): void
    {
        $period = $currentSchedule->examPeriod;

        if ($period === null) {
            return;
        }

        $otherScheduleIds = $period->schedules()
            ->where('room_id', $currentSchedule->room_id)
            ->where('id', '!=', $currentSchedule->id)
            ->pluck('id')
            ->all();

        if ($otherScheduleIds === []) {
            return;
        }

        if ($overwriteExisting) {
            $sourceSession = ExamSession::query()
                ->where('student_id', $student->id)
                ->where('exam_schedule_id', $currentSchedule->id)
                ->where('attendance_confirmed', true)
                ->first();

            if ($sourceSession === null) {
                return;
            }

            foreach ($otherScheduleIds as $otherId) {
                $existing = ExamSession::query()
                    ->where('student_id', $student->id)
                    ->where('exam_schedule_id', $otherId)
                    ->first();

                if ($existing === null) {
                    ExamSession::query()->create([
                        'student_id' => $student->id,
                        'exam_schedule_id' => $otherId,
                        'status' => ExamSession::STATUS_NOT_STARTED,
                        'attendance_confirmed' => true,
                        'attendance_confirmed_at' => $sourceSession->attendance_confirmed_at,
                        'attendance_confirmed_by' => $sourceSession->attendance_confirmed_by,
                        'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                    ]);
                } elseif (! $existing->attendance_confirmed) {
                    $existing->update([
                        'attendance_confirmed' => true,
                        'attendance_confirmed_at' => $sourceSession->attendance_confirmed_at,
                        'attendance_confirmed_by' => $sourceSession->attendance_confirmed_by,
                        'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                    ]);
                }
            }
        } else {
            $sourceSession = ExamSession::query()
                ->where('student_id', $student->id)
                ->whereIn('exam_schedule_id', $otherScheduleIds)
                ->where('attendance_confirmed', true)
                ->first();

            if ($sourceSession === null) {
                return;
            }

            ExamSession::query()
                ->where('student_id', $student->id)
                ->where('exam_schedule_id', $currentSchedule->id)
                ->where(function ($q) {
                    $q->whereNull('attendance_confirmed')
                        ->orWhere('attendance_confirmed', false);
                })
                ->update([
                    'attendance_confirmed' => true,
                    'attendance_confirmed_at' => $sourceSession->attendance_confirmed_at,
                    'attendance_confirmed_by' => $sourceSession->attendance_confirmed_by,
                    'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                ]);
        }
    }
}
