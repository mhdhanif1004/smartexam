<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Student;
use App\Traits\ScopesSupervisorRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TokenController extends Controller
{
    use ScopesSupervisorRoom;

    public function index(): View
    {
        $room = $this->supervisorRoom();

        // Pengawas sah tapi belum ditugaskan ke ruangan mana pun hari ini —
        // bukan pelanggaran akses, render empty state.
        if ($room === null) {
            return view('pengawas.tokens.index', [
                'room' => null,
                'period' => null,
                'activeToken' => null,
                'nextRotationAt' => null,
                'rotationHistory' => collect(),
                'students' => collect(),
                'stats' => ['sudah_token' => 0, 'belum_token' => 0],
            ]);
        }

        $period = $this->currentPeriod();

        if ($period === null) {
            return view('pengawas.tokens.index', [
                'room' => $room,
                'period' => null,
                'activeToken' => null,
                'nextRotationAt' => null,
                'rotationHistory' => collect(),
                'students' => collect(),
                'stats' => ['sudah_token' => 0, 'belum_token' => 0],
            ]);
        }

        $activeToken = ExamToken::where('exam_period_id', $period->id)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->first();

        $nextRotationAt = null;
        if ($activeToken !== null) {
            $nextRotationAt = $activeToken->valid_until;
        }

        $rotationHistory = ExamToken::where('exam_period_id', $period->id)
            ->orderByDesc('rotation_index')
            ->get();

        $scheduleIds = $period->schedules()
            ->where('room_id', $room->id)
            ->pluck('id');

        // Peserta sesi ini di ruangan pengawas berasal dari penempatan
        // exam_room_assignments (sama seperti halaman absensi), BUKAN dari
        // home-room students.room_id — siswa bisa ditempatkan ke ruangan ujian
        // yang berbeda dari ruangan asalnya.
        $participantIds = $period->schedules()
            ->where('room_id', $room->id)
            ->get()
            ->pipe(fn ($schedules) => ExamSchedule::participantStudentIdsBySchedules($schedules))
            ->flatten()
            ->unique()
            ->values();

        $students = Student::query()
            ->with(['user', 'examSessions' => fn ($q) => $q->whereIn('exam_schedule_id', $scheduleIds)])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->whereIn('students.id', $participantIds)
            ->orderBy('students.class_name')
            ->orderBy('users.name')
            ->orderBy('students.nisn')
            ->select('students.*')
            ->get();

        $stats = ['sudah_token' => 0, 'belum_token' => 0];
        foreach ($students as $student) {
            $status = $student->examSessions->first()?->status ?? ExamSession::STATUS_NOT_STARTED;
            if (in_array($status, [ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED], true)) {
                $stats['sudah_token']++;
            } else {
                $stats['belum_token']++;
            }
        }

        return view('pengawas.tokens.index', compact('room', 'period', 'activeToken', 'nextRotationAt', 'rotationHistory', 'students', 'stats'));
    }

    public function currentToken(): JsonResponse
    {
        $room = $this->supervisorRoom();

        if ($room === null) {
            return response()->json(['active' => false]);
        }

        $period = $this->currentPeriod();

        if ($period === null) {
            return response()->json(['active' => false]);
        }

        $activeToken = ExamToken::where('exam_period_id', $period->id)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->first();

        if ($activeToken === null) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'token_code' => $activeToken->token_code,
            'rotation_index' => $activeToken->rotation_index,
            'remaining_seconds' => max(0, $activeToken->valid_until->getTimestamp() - now()->getTimestamp()),
        ]);
    }
}
