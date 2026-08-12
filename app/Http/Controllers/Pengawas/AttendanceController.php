<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
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
        $schedules = $this->windowSchedules($room, 10);
        $schedule = $this->currentSchedule($room, $request->integer('schedule') ?: null, 10);
        $students = $schedule !== null ? $this->attendanceRows($schedule) : collect();
        $upcomingSchedules = $this->upcomingSchedules($room, 10);

        return view('pengawas.attendance.index', compact('room', 'schedules', 'schedule', 'students', 'upcomingSchedules'));
    }

    /**
     * Perbarui kehadiran satu siswa lewat AJAX (PATCH).
     */
    public function confirm(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $schedule->syncStatusIfNeeded();

        $room = $this->supervisorRoom();
        $ongoing = $this->currentSchedule($room, $schedule->id, 10);

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

        return response()->json(['ok' => true]);
    }

    public function update(Request $request): RedirectResponse
    {
        $room = $this->supervisorRoom();
        $schedule = $this->currentSchedule($room, $request->integer('schedule') ?: null, 10);

        abort_if($schedule === null, 404, 'Tidak ada sesi ujian yang sedang dalam jendela absensi di ruangan Anda.');

        $schedule->syncStatusIfNeeded();

        $validator = Validator::make($request->all(), [
            'attendance' => ['required', 'array'],
            'attendance.*' => ['required', Rule::in(array_keys(ExamSession::ATTENDANCE_STATUSES))],
        ]);

        $validator->after(function ($validator) use ($request, $schedule) {
            $participantIds = $schedule->participantStudentIds();

            foreach (array_keys($request->input('attendance', [])) as $studentId) {
                if (! in_array((int) $studentId, $participantIds, true)) {
                    $validator->errors()->add('attendance', 'Siswa bukan peserta pada sesi ujian ini.');

                    return;
                }
            }
        })->validate();

        foreach ($request->input('attendance') as $studentId => $status) {
            $confirmed = $status === ExamSession::ATTENDANCE_PRESENT;

            ExamSession::updateOrCreate(
                ['student_id' => $studentId, 'exam_schedule_id' => $schedule->id],
                [
                    'attendance_status' => $status,
                    'attendance_confirmed' => $confirmed,
                    'attendance_confirmed_at' => now(),
                    'attendance_confirmed_by' => auth()->id(),
                ]
            );
        }

        return back()->with('success', 'Absensi peserta berhasil disimpan.');
    }

    /**
     * Daftar siswa peserta lengkap dengan sesi ujian (dibuat otomatis bila
     * belum ada) serta jumlah pelanggaran untuk deteksi "nonaktif otomatis".
     *
     * @return Collection<int, Student>
     */
    private function attendanceRows(ExamSchedule $schedule): Collection
    {
        $students = Student::query()
            ->with('user')
            ->whereIn('id', $schedule->participantStudentIds())
            ->orderBy('nisn')
            ->get();

        $sessions = ExamSession::query()
            ->where('exam_schedule_id', $schedule->id)
            ->withCount('violations')
            ->get()
            ->keyBy('student_id');

        foreach ($students as $student) {
            $session = $sessions->get($student->id);

            if ($session === null) {
                $session = ExamSession::query()->create([
                    'student_id' => $student->id,
                    'exam_schedule_id' => $schedule->id,
                    'status' => ExamSession::STATUS_NOT_STARTED,
                ]);
            }

            $student->setRelation('examSession', $session);
        }

        return $students;
    }
}
