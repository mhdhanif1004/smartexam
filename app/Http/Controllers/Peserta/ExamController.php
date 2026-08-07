<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Question;
use App\Models\Student;
use App\Services\ExamGradingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExamController extends Controller
{
    public const ACCESS_ERROR_NOT_CONFIRMED = 'Anda belum diabsen oleh pengawas ruangan. Silakan hubungi pengawas untuk absensi terlebih dahulu sebelum memasukkan token.';

    public const ACCESS_ERROR_DISABLED_BY_VIOLATION = 'Absensi Anda dinonaktifkan sistem karena terdeteksi pelanggaran. Silakan hubungi pengawas ruangan untuk diabsenkan kembali sebelum melanjutkan ujian.';

    public const ACCESS_ERROR_LOCKED_ADMIN = 'Ujian Anda dihentikan oleh Administrator. Silakan hubungi Administrator secara langsung untuk melanjutkan ujian mata pelajaran ini.';

    private ?Student $student = null;

    private ?ExamSchedule $schedule = null;

    public function __construct(
        private readonly ExamGradingService $grading,
    ) {}

    public function token(Request $request, int $schedule): View|RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->existingSession();

        if ($session !== null && $session->status === ExamSession::STATUS_COMPLETED) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if ($session !== null && $session->status === ExamSession::STATUS_IN_PROGRESS) {
            if (($error = $this->accessBlock($session)) !== null) {
                return view('peserta.exams.token', ['schedule' => $this->schedule, 'accessError' => $error]);
            }

            return redirect()->route('peserta.exams.work', $this->schedule->id);
        }

        if (($redirect = $this->timingGuard()) !== null) {
            return $redirect;
        }

        return view('peserta.exams.token', [
            'schedule' => $this->schedule,
            'accessError' => $session === null ? self::ACCESS_ERROR_NOT_CONFIRMED : $this->accessBlock($session),
        ]);
    }

    public function validateToken(Request $request, int $schedule): RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        if (($redirect = $this->timingGuard()) !== null) {
            return $redirect;
        }

        $session = $this->existingSession();

        if ($session !== null && $session->status === ExamSession::STATUS_COMPLETED) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if ($session === null) {
            return back()->with('error', self::ACCESS_ERROR_NOT_CONFIRMED);
        }

        if (($error = $this->accessBlock($session)) !== null) {
            return back()->with('error', $error);
        }

        if ($session->status === ExamSession::STATUS_IN_PROGRESS) {
            return redirect()->route('peserta.exams.work', $this->schedule->id);
        }

        $tokenCode = strtoupper(trim((string) $request->string('token_code')));

        $token = ExamToken::query()
            ->where('exam_schedule_id', $this->schedule->id)
            ->where('token_code', $tokenCode)
            ->where('valid_until', '>', now())
            ->first();

        if ($token === null) {
            return back()->with('error', 'Token ujian salah atau sudah tidak berlaku.');
        }

        $session->update([
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return redirect()->route('peserta.exams.work', $this->schedule->id)
            ->with('success', 'Token valid. Selamat mengerjakan!');
    }

    public function work(Request $request, int $schedule): View|RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->sessionFor();

        if ($session->status === ExamSession::STATUS_COMPLETED) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if (($error = $this->midExamBlock($session)) !== null) {
            return $this->deny($error);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return $this->deny('Sesi ujian belum dimulai. Masukkan token terlebih dahulu.');
        }

        $questions = $this->schedule->subject->questions()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $questionsData = $questions
            ->map(fn (Question $question) => [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'options' => $question->options,
                'score_weight' => (float) $question->score_weight,
            ])
            ->values();

        $savedAnswers = $session->examAnswers()
            ->get()
            ->keyBy('question_id')
            ->map(fn (ExamAnswer $answer) => $answer->student_answer);

        $deadline = $this->deadline($session)->timestamp;

        return view('peserta.exams.work', [
            'schedule' => $this->schedule,
            'session' => $session,
            'questionsData' => $questionsData,
            'savedAnswers' => $savedAnswers,
            'deadline' => $deadline,
        ]);
    }

    public function saveAnswer(Request $request, int $schedule): JsonResponse
    {
        $student = auth()->user()?->student;
        if (! $student instanceof Student) {
            return response()->json(['error' => 'Akun ini tidak terdaftar sebagai peserta.'], 403);
        }

        $schedule = ExamSchedule::query()
            ->with('subject')
            ->whereKey($schedule)
            ->where('room_id', $student->room_id)
            ->first();

        if ($schedule === null) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = $this->firstOrCreateSession($student->id, $schedule->id);

        if (($error = $this->midExamBlock($session)) !== null) {
            return response()->json(['error' => $error], 403);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return response()->json(['error' => 'Sesi ujian belum dimulai.'], 403);
        }

        if (now()->gt($this->deadline($session, $schedule))) {
            return response()->json(['expired' => true], 422);
        }

        $this->storeAnswers($session, $schedule, (array) $request->input('answers', []));

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, int $schedule): JsonResponse
    {
        $student = auth()->user()?->student;
        if (! $student instanceof Student) {
            return response()->json(['error' => 'Akun ini tidak terdaftar sebagai peserta.'], 403);
        }

        $schedule = ExamSchedule::query()
            ->whereKey($schedule)
            ->where('room_id', $student->room_id)
            ->first();

        if ($schedule === null) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = ExamSession::query()
            ->where('student_id', $student->id)
            ->where('exam_schedule_id', $schedule->id)
            ->first();

        if ($session === null || $session->status !== ExamSession::STATUS_IN_PROGRESS) {
            return response()->json(['active' => false]);
        }

        if ($session->locked_by_admin) {
            return response()->json([
                'locked' => true,
                'message' => self::ACCESS_ERROR_LOCKED_ADMIN,
            ]);
        }

        return response()->json(['locked' => false]);
    }

    public function submit(Request $request, int $schedule): RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->sessionFor();

        if ($session->status === ExamSession::STATUS_COMPLETED) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if (($error = $this->midExamBlock($session)) !== null) {
            return $this->deny($error);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return $this->deny('Sesi ujian belum dimulai.');
        }

        $this->storeAnswers($session, $this->schedule, (array) $request->input('answers', []));
        $this->grading->finalize($session, $this->schedule);

        return redirect()->route('peserta.exams.finished', $this->schedule->id)
            ->with('success', 'Ujian berhasil dikumpulkan.');
    }

    public function finished(Request $request, int $schedule): View|RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->sessionFor();

        if ($session->status !== ExamSession::STATUS_COMPLETED || $session->finished_at === null) {
            return $this->deny('Ujian belum selesai dikerjakan.');
        }

        $session->load(['examSchedule.subject', 'examSchedule.room', 'examResult']);

        $result = $session->examResult;
        $workingSeconds = (int) $session->finished_at->diffInSeconds($session->started_at);
        $answeredCount = $session->examAnswers()->count();

        return view('peserta.exams.finished', compact('session', 'result', 'workingSeconds', 'answeredCount'));
    }

    /**
     * Pastikan jadwal ujian milik peserta yang login, lalu siapkan properti.
     */
    private function resolve(Request $request, int $scheduleId): ?RedirectResponse
    {
        $this->student = auth()->user()?->student;

        if (! $this->student instanceof Student) {
            return $this->deny('Akun Anda tidak terdaftar sebagai peserta.');
        }

        $this->schedule = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->whereKey($scheduleId)
            ->where('room_id', $this->student->room_id)
            ->first();

        if ($this->schedule === null) {
            return $this->deny('Anda tidak memiliki akses ke ujian tersebut.');
        }

        return null;
    }

    /**
     * Periksa waktu ujian: harus hari ini, belum lewat jam mulai, dan belum
     * melewati jam selesai.
     */
    private function timingGuard(): ?RedirectResponse
    {
        if (! $this->schedule->exam_date->isToday()) {
            return $this->deny('Ujian ini tidak dijadwalkan hari ini.');
        }

        if (now()->format('H:i:s') < $this->schedule->start_time) {
            return $this->deny('Ujian belum waktunya dimulai.');
        }

        if (now()->format('H:i:s') > $this->schedule->end_time) {
            return $this->deny('Waktu ujian sudah berakhir.');
        }

        return null;
    }

    private function sessionFor(): ExamSession
    {
        return $this->firstOrCreateSession($this->student->id, $this->schedule->id);
    }

    /**
     * Ambil sesi ujian yang sudah ada tanpa membuat yang baru. Dipakai pada
     * halaman validasi token agar siswa tanpa catatan absensi tidak
     * mendapatkan sesi (harus diabsen dulu oleh pengawas).
     */
    private function existingSession(): ?ExamSession
    {
        return ExamSession::query()
            ->where('student_id', $this->student->id)
            ->where('exam_schedule_id', $this->schedule->id)
            ->first();
    }

    /**
     * Pesan penghalang sebelum token bisa diproses: lock admin mengambil
     * prioritas tertinggi, lalu absensi yang dinonaktifkan sistem, lalu
     * siswa yang belum diabsen. Mengembalikan null bila siswa boleh lanjut.
     */
    private function accessBlock(ExamSession $session): ?string
    {
        if ($session->locked_by_admin) {
            return self::ACCESS_ERROR_LOCKED_ADMIN;
        }

        if (! $session->attendance_confirmed) {
            return $session->activeViolationFlags() > 0
                ? self::ACCESS_ERROR_DISABLED_BY_VIOLATION
                : self::ACCESS_ERROR_NOT_CONFIRMED;
        }

        return null;
    }

    /**
     * Penghalang untuk sesi yang sedang berjalan: hentikan bila dikunci admin
     * atau bila absensi dinonaktifkan karena pelanggaran otomatis. Sesi
     * in_progress yang belum diabsen tanpa pelanggaran tetap diizinkan
     * (mis. saat pengawas mengubah absensi di tengah ujian).
     */
    private function midExamBlock(ExamSession $session): ?string
    {
        if ($session->locked_by_admin) {
            return self::ACCESS_ERROR_LOCKED_ADMIN;
        }

        if (! $session->attendance_confirmed && $session->activeViolationFlags() > 0) {
            return self::ACCESS_ERROR_DISABLED_BY_VIOLATION;
        }

        return null;
    }

    /**
     * Buat sesi ujian dengan aman dari kondisi balapan (race condition).
     * Unique index (student_id, exam_schedule_id) mencegah duplikat; jika
     * dua request bersamaan mencoba membuat sesi yang sama, yang kalah akan
     * mengambil ulang record yang sudah dibuat.
     */
    private function firstOrCreateSession(int $studentId, int $scheduleId): ExamSession
    {
        try {
            return ExamSession::query()->firstOrCreate(
                ['student_id' => $studentId, 'exam_schedule_id' => $scheduleId],
                ['status' => ExamSession::STATUS_NOT_STARTED],
            );
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }

            return ExamSession::query()
                ->where('student_id', $studentId)
                ->where('exam_schedule_id', $scheduleId)
                ->firstOrFail();
        }
    }

    private function deadline(ExamSession $session, ?ExamSchedule $schedule = null): Carbon
    {
        $schedule ??= $this->schedule;

        return $session->started_at->copy()->addMinutes((int) $schedule->duration_minutes);
    }

    /**
     * Simpan atau hapus jawaban peserta (jawaban kosong dihapus agar jumlah
     * "soal dijawab" akurat). Hanya soal milik mata pelajaran ujian yang
     * diterima.
     *
     * @param  array<mixed>  $answers
     */
    private function storeAnswers(ExamSession $session, ExamSchedule $schedule, array $answers): void
    {
        $validQuestionIds = $schedule->subject->questions()
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $validQuestionSet = array_flip($validQuestionIds);

        $toUpsert = [];
        $toDelete = [];

        foreach ($answers as $questionId => $value) {
            $questionId = (int) $questionId;

            if (! isset($validQuestionSet[$questionId])) {
                continue;
            }

            if ($value === null || $value === '' || $value === []) {
                $toDelete[] = $questionId;

                continue;
            }

            $toUpsert[] = [
                'exam_session_id' => $session->id,
                'question_id' => $questionId,
                'student_answer' => json_encode($value),
            ];
        }

        if ($toUpsert !== []) {
            ExamAnswer::query()->upsert($toUpsert, ['exam_session_id', 'question_id'], ['student_answer']);
        }

        if ($toDelete !== []) {
            ExamAnswer::query()
                ->where('exam_session_id', $session->id)
                ->whereIn('question_id', $toDelete)
                ->delete();
        }
    }

    private function deny(string $message): RedirectResponse
    {
        return redirect()->route('peserta.dashboard')->with('error', $message);
    }
}
