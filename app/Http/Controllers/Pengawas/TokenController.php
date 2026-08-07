<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamToken;
use App\Traits\ScopesSupervisorRoom;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TokenController extends Controller
{
    use ScopesSupervisorRoom;

    public function index(Request $request): View
    {
        $room = $this->supervisorRoom();
        $schedules = $this->ongoingSchedules($room);
        $schedule = $this->currentSchedule($room, $request->integer('schedule') ?: null);
        $students = $schedule !== null ? $this->participants($schedule) : collect();
        $token = $schedule !== null ? $this->activeToken($schedule->id) : null;

        return view('pengawas.tokens.index', compact('room', 'schedules', 'schedule', 'students', 'token'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $room = $this->supervisorRoom();
        $schedule = $this->currentSchedule($room, $request->integer('schedule') ?: null);

        abort_if($schedule === null, 404, 'Tidak ada sesi ujian yang sedang berlangsung di ruangan Anda.');

        ExamToken::where('exam_schedule_id', $schedule->id)->delete();

        // valid_until berbasis waktu SELESAI ujian sesuai jadwal, bukan dari now()
        $examEndTime = Carbon::parse($schedule->exam_date->format('Y-m-d').' '.$schedule->start_time)
            ->addMinutes((int) $schedule->duration_minutes);

        ExamToken::create([
            'exam_schedule_id' => $schedule->id,
            'token_code' => strtoupper(Str::random(8)),
            'valid_until' => $examEndTime,
        ]);

        return back()->with('success', 'Token ujian baru berhasil dibuat.');
    }

    private function activeToken(int $scheduleId): ?ExamToken
    {
        return ExamToken::query()
            ->where('exam_schedule_id', $scheduleId)
            ->where('valid_until', '>', now())
            ->latest('id')
            ->first();
    }
}
