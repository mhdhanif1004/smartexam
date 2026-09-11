<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Student;
use App\Models\Violation;
use App\Traits\ScopesGuruMapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mode "Pengawas Mandiri" Guru Mapel.
 *
 * Hanya bisa diakses untuk ExamPeriod milik guru itu sendiri (middleware
 * `owner` di route group). Semua action di-scope ke period ini — bukan
 * berdasarkan ruangan seperti pengawas asli.
 *
 * Logika absensi (tri-state hadir/tidak_hadir/clear + guard locked_by_admin)
 * mengikuti semantik Pengawas\AttendanceController::confirm, namun tanpa
 * scoping ruangan dan tanpa propagasi antar-jadwal (period guru biasanya
 * berisi satu jadwal; integritas lintas-jadwal tidak relevan di sini).
 */
class ProctorController extends Controller
{
    use ScopesGuruMapel;

    public function show(ExamPeriod $examPeriod): View
    {
        $guru = $this->currentGuru();

        $examPeriod->load(['examType', 'schedules.subject']);

        $schedules = $examPeriod->schedules()
            ->with(['subject', 'classroom'])
            ->get();

        $participantIdsBySchedule = ExamSchedule::participantStudentIdsBySchedules($schedules);

        $statsBySchedule = [];
        $students = collect();

        foreach ($schedules as $schedule) {
            $participantIds = $participantIdsBySchedule->get($schedule->id, []);
            $studentModels = Student::query()
                ->with(['user', 'examSessions' => fn ($q) => $q->where('exam_schedule_id', $schedule->id)])
                ->whereIn('id', $participantIds)
                ->orderBy('nisn')
                ->get();

            if ($students->isEmpty()) {
                $students = $studentModels;
            }

            $sessions = ExamSession::query()->where('exam_schedule_id', $schedule->id)->get();

            $statsBySchedule[$schedule->id] = [
                'schedule' => $schedule,
                'total' => count($participantIds),
                'hadir' => $sessions->where('attendance_confirmed', true)->count(),
                'sedang_mengerjakan' => $sessions->where('status', ExamSession::STATUS_IN_PROGRESS)->count(),
                'selesai' => $sessions->where('status', ExamSession::STATUS_COMPLETED)->count(),
                'students' => $studentModels,
            ];
        }

        $violations = Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject'])
            ->whereHas('examSession', fn ($q) => $q->whereIn('exam_schedule_id', $examPeriod->schedules()->pluck('id')))
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $activeToken = ExamToken::query()
            ->where('exam_period_id', $examPeriod->id)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->orderByDesc('valid_from')
            ->first();

        return view('guru_mapel.proctor.show', compact(
            'guru', 'examPeriod', 'statsBySchedule', 'students', 'violations', 'activeToken'
        ));
    }

    /**
     * Token aktif untuk period ini (JSON).
     */
    public function token(ExamPeriod $examPeriod): JsonResponse
    {
        $token = ExamToken::query()
            ->where('exam_period_id', $examPeriod->id)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->orderByDesc('valid_from')
            ->first();

        $nextRotation = ExamToken::query()
            ->where('exam_period_id', $examPeriod->id)
            ->where('valid_from', '>', now())
            ->orderBy('valid_from')
            ->value('valid_from');

        return response()->json([
            'active' => $token !== null,
            'token_code' => $token?->token_code,
            'valid_until' => $token?->valid_until,
            'next_rotation_at' => $nextRotation,
        ]);
    }

    /**
     * Daftar pelanggaran untuk period ini (JSON).
     */
    public function violations(ExamPeriod $examPeriod): JsonResponse
    {
        $scheduleIds = $examPeriod->schedules()->pluck('id');

        $violations = Violation::query()
            ->with(['examSession.student.user'])
            ->whereHas('examSession', fn ($q) => $q->whereIn('exam_schedule_id', $scheduleIds))
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (Violation $violation) => [
                'id' => $violation->id,
                'student_name' => $violation->examSession?->student?->user?->name ?? '-',
                'nisn' => $violation->examSession?->student?->nisn ?? '-',
                'type' => Violation::typeLabel($violation->violation_type),
                'occurred_at' => $violation->occurred_at?->format('d/m/Y H:i'),
                'handled' => (bool) $violation->handled_by_supervisor,
            ]);

        return response()->json(['violations' => $violations->values()->all()]);
    }

    /**
     * Confirm / revoke kehadiran siswa (tri-state hadir/tidak_hadir/clear),
     * scoped ke exam_period milik guru ini. Satu-satunya jalur yang menulis
     * attendance_confirmed supaya siswa bisa masuk token (standar integritas
     * sama dengan pengawas asli — TIDAK di-bypass).
     */
    public function confirmAttendance(Request $request, ExamPeriod $examPeriod, ExamSchedule $schedule): JsonResponse
    {
        // Pertahanan: jadwal harus bagian dari period ini.
        if ($schedule->exam_period_id !== $examPeriod->id) {
            return response()->json(['error' => 'Jadwal bukan bagian dari sesi ujian ini.'], 422);
        }

        $schedule->syncStatusIfNeeded();

        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in(['hadir', 'tidak_hadir'])],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        if (! $request->has('status') && ! $request->has('confirmed')) {
            return response()->json(['message' => 'Status kehadiran wajib diisi.'], 422);
        }

        $status = $request->has('status')
            ? $request->input('status') // bisa null, 'hadir', 'tidak_hadir'
            : ($request->boolean('confirmed') ? ExamSession::ATTENDANCE_PRESENT : ExamSession::ATTENDANCE_ABSENT);

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

        if ($status === null) {
            $session->attendance_status = null;
            $session->attendance_confirmed = false;
            $session->attendance_confirmed_at = null;
            $session->attendance_confirmed_by = null;
        } else {
            $session->attendance_status = $status;
            $session->attendance_confirmed = true;
            $session->attendance_confirmed_at = now();
            $session->attendance_confirmed_by = auth()->id();
        }

        $session->save();

        return response()->json([
            'ok' => true,
            'message' => 'Kehadiran berhasil diperbarui.',
            'status' => $session->attendance_status,
            'attendance_confirmed' => $session->attendance_confirmed,
        ]);
    }
}
