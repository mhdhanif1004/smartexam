<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\ExamAnswer;
use App\Models\ExamPeriod;
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

        if ($session !== null && $session->isTerminal()) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if ($session !== null && $session->status === ExamSession::STATUS_IN_PROGRESS) {
            if (($error = $this->accessBlock($session)) !== null) {
                return view('peserta.exams.token', ['schedule' => $this->schedule, 'student' => $this->student, 'accessError' => $error]);
            }

            return redirect()->route('peserta.exams.work', $this->schedule->id);
        }

        if (($redirect = $this->timingGuard()) !== null) {
            return $redirect;
        }

        return view('peserta.exams.token', [
            'schedule' => $this->schedule,
            'student' => $this->student,
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

        if ($session !== null && $session->isTerminal()) {
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

        if (! $this->subjectHasActiveQuestions()) {
            return back()->with('error', 'Belum ada soal tersedia untuk ujian ini. Silakan hubungi pengawas/admin.');
        }

        $tokenCode = strtoupper(trim((string) $request->string('token_code')));

        $periodId = $this->schedule->exam_period_id;

        $token = ExamToken::query()
            ->where('exam_period_id', $periodId)
            ->where('token_code', $tokenCode)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->first();

        if ($token === null) {
            return back()->with('error', 'Token ujian salah atau sudah tidak berlaku.');
        }

        $deadlineType = $this->schedule->computedStatus() === ExamSchedule::STATUS_FINISHED
            ? ExamSession::DEADLINE_TYPE_DURATION
            : ExamSession::DEADLINE_TYPE_SCHEDULE_END;

        $session->update([
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => $session->started_at ?? now(),
            'deadline_type' => $session->deadline_type ?? $deadlineType,
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

        if ($session->isTerminal()) {
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
            ->targetingClassroom($this->student->classroom_id)
            ->orderBy('id')
            ->get();

        $questionsData = $questions
            ->map(fn (Question $question) => [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'image_url' => $question->imageUrl(),
                'options' => $question->options,
                'score_weight' => (float) $question->score_weight,
            ])
            ->values();

        $savedAnswers = $session->examAnswers()
            ->get()
            ->keyBy('question_id')
            ->map(fn (ExamAnswer $answer) => $answer->student_answer);

        $doubtfulQuestions = $session->examAnswers()
            ->where('is_doubtful', true)
            ->pluck('question_id')
            ->mapWithKeys(fn (int $id) => [$id => true]);

        $deadline = $this->deadline($session)->timestamp;

        $period = $this->schedule->examPeriod;

        if ($period !== null) {
            $periodEnd = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->end_time);
            $periodStart = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->start_time);
            $graceMinutes = config('exam.grace_period_minutes', 10);
            $graceEnd = $periodEnd->copy()->addMinutes($graceMinutes);
            $totalSessionSeconds = max(0, $periodEnd->getTimestamp() - $periodStart->getTimestamp());
            // Tahap 1: sisa waktu resmi (periodEnd), tanpa grace. Bisa bernilai nol bila periode sudah lewat.
            $remainingSession = max(0, $periodEnd->getTimestamp() - now()->getTimestamp());
            // Tahap 2: sisa masa toleransi (periodEnd + grace). Hanya relevan bila tahap 1 habis.
            $remainingGrace = max(0, $graceEnd->getTimestamp() - now()->getTimestamp());
            $isFinalMapel = $this->computeIsFinalMapel($this->schedule, $period);
        } else {
            $mapelRemaining = max(0, $deadline - now()->getTimestamp());
            $totalSessionSeconds = $mapelRemaining;
            $remainingSession = $mapelRemaining;
            $remainingGrace = 0;
            $isFinalMapel = true;
        }

        return view('peserta.exams.work', [
            'schedule' => $this->schedule,
            'session' => $session,
            'questionsData' => $questionsData,
            'savedAnswers' => $savedAnswers,
            'doubtfulQuestions' => $doubtfulQuestions,
            'deadline' => $deadline,
            'totalSessionSeconds' => $totalSessionSeconds,
            'remainingSession' => $remainingSession,
            'remainingGrace' => $remainingGrace,
            'isFinalMapel' => $isFinalMapel,
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
            ->find($schedule);

        if ($schedule === null || ! $student->isAssignedToSchedule($schedule)) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = $this->firstOrCreateSession($student->id, $schedule->id);

        if (($error = $this->midExamBlock($session)) !== null) {
            return response()->json(['error' => $error], 403);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return response()->json(['error' => 'Sesi ujian belum dimulai.'], 403);
        }

        if ($schedule->isExpiredAfterGrace($session)) {
            return response()->json(['expired' => true], 422);
        }

        $session->touchLastActivity();

        $this->storeAnswers($session, $schedule, (array) $request->input('answers', []), $student->classroom_id);

        return response()->json(['ok' => true]);
    }

    public function toggleDoubtful(Request $request, int $schedule, int $question): JsonResponse
    {
        $student = auth()->user()?->student;
        if (! $student instanceof Student) {
            return response()->json(['error' => 'Akun ini tidak terdaftar sebagai peserta.'], 403);
        }

        $schedule = ExamSchedule::query()
            ->with('subject')
            ->find($schedule);

        if ($schedule === null || ! $student->isAssignedToSchedule($schedule)) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = $this->firstOrCreateSession($student->id, $schedule->id);

        if (($error = $this->midExamBlock($session)) !== null) {
            return response()->json(['error' => $error], 403);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return response()->json(['error' => 'Sesi ujian belum dimulai.'], 403);
        }

        if ($schedule->isExpiredAfterGrace($session)) {
            return response()->json(['expired' => true], 422);
        }

        $belongsToExam = $schedule->subject->questions()
            ->where('is_active', true)
            ->whereKey($question)
            ->targetingClassroom($student->classroom_id)
            ->exists();

        if (! $belongsToExam) {
            return response()->json(['error' => 'Soal tidak tersedia.'], 422);
        }

        $answer = ExamAnswer::query()
            ->where('exam_session_id', $session->id)
            ->where('question_id', $question)
            ->first();

        if ($answer === null) {
            ExamAnswer::create([
                'exam_session_id' => $session->id,
                'question_id' => $question,
                'is_doubtful' => true,
            ]);

            return response()->json(['ok' => true, 'question_id' => $question, 'is_doubtful' => true]);
        }

        $isDoubtful = ! $answer->is_doubtful;

        if (! $isDoubtful && $answer->student_answer === null) {
            $answer->delete();
        } else {
            $answer->update(['is_doubtful' => $isDoubtful]);
        }

        return response()->json(['ok' => true, 'question_id' => $question, 'is_doubtful' => $isDoubtful]);
    }

    public function status(Request $request, int $schedule): JsonResponse
    {
        $student = auth()->user()?->student;
        if (! $student instanceof Student) {
            return response()->json(['error' => 'Akun ini tidak terdaftar sebagai peserta.'], 403);
        }

        $schedule = ExamSchedule::query()
            ->find($schedule);

        if ($schedule === null || ! $student->isAssignedToSchedule($schedule)) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = ExamSession::query()
            ->where('student_id', $student->id)
            ->where('exam_schedule_id', $schedule->id)
            ->first();

        if ($session === null || $session->status !== ExamSession::STATUS_IN_PROGRESS) {
            return response()->json(['active' => false]);
        }

        $session->touchLastActivity();

        if ($session->locked_by_admin) {
            return response()->json([
                'locked' => true,
                'message' => self::ACCESS_ERROR_LOCKED_ADMIN,
            ]);
        }

        $remainingMapel = max(0, $this->deadline($session, $schedule)->getTimestamp() - now()->getTimestamp());

        $period = $schedule->examPeriod;

        if ($period !== null) {
            $periodEnd = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->end_time);
            $periodStart = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->start_time);
            $graceMinutes = config('exam.grace_period_minutes', 10);
            $graceEnd = $periodEnd->copy()->addMinutes($graceMinutes);
            $nowTs = now()->getTimestamp();
            // Tahap 1: sisa waktu resmi (periodEnd), tanpa grace. Bisa negatif bila periode sudah lewat.
            $remainingSesi = $periodEnd->getTimestamp() - $nowTs;
            // Tahap 2: sisa masa toleransi (periodEnd + grace). Bisa negatif bila grace sudah habis.
            $remainingGrace = $graceEnd->getTimestamp() - $nowTs;
            $totalSesi = max(0, $periodEnd->getTimestamp() - $periodStart->getTimestamp());
            $isFinalMapel = $this->computeIsFinalMapel($schedule, $period);
        } else {
            $remainingSesi = $remainingMapel;
            $remainingGrace = 0;
            $totalSesi = $remainingMapel;
            $isFinalMapel = true;
        }

        return response()->json([
            'locked' => false,
            'mapel' => [
                'remaining_seconds' => $remainingMapel,
                'is_final' => $isFinalMapel,
            ],
            'sesi' => [
                'remaining_seconds' => $remainingSesi,
                'remaining_grace' => $remainingGrace,
                'in_grace' => $remainingSesi <= 0 && $remainingGrace > 0,
                'total_seconds' => $totalSesi,
            ],
        ]);
    }

    public function submit(Request $request, int $schedule): RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->sessionFor();

        if ($session->isTerminal()) {
            return redirect()->route('peserta.exams.finished', $this->schedule->id);
        }

        if (($error = $this->midExamBlock($session)) !== null) {
            return $this->deny($error);
        }

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return $this->deny('Sesi ujian belum dimulai.');
        }

        $isExpired = $this->schedule->isExpiredAfterGrace($session);

        if (! $isExpired) {
            $this->storeAnswers($session, $this->schedule, (array) $request->input('answers', []), $this->student->classroom_id);
        }

        $this->grading->finalize($session, $this->schedule);

        return redirect()->route('peserta.exams.finished', $this->schedule->id)
            ->with($isExpired ? 'warning' : 'success',
                $isExpired
                    ? 'Waktu ujian telah habis. Jawaban dikumpulkan otomatis.'
                    : 'Ujian berhasil dikumpulkan.');
    }

    public function finished(Request $request, int $schedule): View|RedirectResponse
    {
        if (($redirect = $this->resolve($request, $schedule)) !== null) {
            return $redirect;
        }

        $session = $this->sessionFor();

        if (! $session->isTerminal() || $session->finished_at === null) {
            return $this->deny('Ujian belum selesai dikerjakan.');
        }

        $session->load(['examSchedule.subject', 'examSchedule.room', 'examResult']);

        $result = $session->examResult;
        $workingSeconds = (int) $session->finished_at->diffInSeconds($session->started_at);
        $answeredCount = $session->examAnswers()->whereNotNull('student_answer')->count();

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
            ->find($scheduleId);

        if ($this->schedule !== null) {
            $this->schedule->syncStatusIfNeeded();
        }

        if ($this->schedule === null || ! $this->student->isAssignedToSchedule($this->schedule)) {
            return $this->deny('Anda tidak memiliki akses ke ujian tersebut.');
        }

        return null;
    }

    /**
     * Periksa waktu ujian: harus hari ini, dan jadwal sedang dalam status
     * 'ongoing' real-time. Peserta hanya boleh MENGERJAKAN saat waktu sudah
     * masuk rentang resmi start_time s.d. end_time (jendela 10/5 menit di
     * sisi pengawas TIDAK membuka akses peserta lebih awal).
     */
    private function timingGuard(): ?RedirectResponse
    {
        if (! $this->schedule->exam_date->isToday()) {
            return $this->deny('Ujian ini tidak dijadwalkan hari ini.');
        }

        if ($this->schedule->computedStatus() === ExamSchedule::STATUS_SCHEDULED) {
            // Early-start: siswa sudah selesai mapel lain dalam sesi yang sama,
            // boleh mulai mapel ini lebih awal selama tidak ada sesi paralel aktif.
            if ($this->schedule->exam_period_id !== null
                && $this->schedule->isWithinPeriodWindow()
                && $this->hasCompletedOtherMapelInPeriod()
                && ! $this->hasActiveSessionInPeriod()) {
                return null;
            }

            return $this->deny('Ujian belum waktunya dimulai.');
        }

        // FINISHED tapi masih dalam sesi (ExamPeriod) → izinkan (susulan)
        if ($this->schedule->computedStatus() === ExamSchedule::STATUS_FINISHED
            && ! $this->schedule->isWithinPeriodWindow()) {
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

    private function hasActiveSessionInPeriod(): bool
    {
        if ($this->schedule->exam_period_id === null) {
            return false;
        }

        return ExamSession::query()
            ->where('student_id', $this->student->id)
            ->whereHas('examSchedule', fn ($q) => $q
                ->where('exam_period_id', $this->schedule->exam_period_id)
                ->where('id', '!=', $this->schedule->id))
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->exists();
    }

    private function hasCompletedOtherMapelInPeriod(): bool
    {
        if ($this->schedule->exam_period_id === null) {
            return false;
        }

        return ExamSession::query()
            ->where('student_id', $this->student->id)
            ->whereHas('examSchedule', fn ($q) => $q
                ->where('exam_period_id', $this->schedule->exam_period_id)
                ->where('id', '!=', $this->schedule->id))
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_TIMED_OUT])
            ->exists();
    }

    private function subjectHasActiveQuestions(): bool
    {
        return $this->schedule->subject
            ?->questions()
            ->where('is_active', true)
            ->targetingClassroom($this->student->classroom_id)
            ->exists() ?? false;
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

    private function deadline(ExamSession $session, ?ExamSchedule $schedule = null): \Carbon\Carbon
    {
        return $session->deadline($schedule ?? $this->schedule);
    }

    /**
     * Tentukan apakah schedule ini adalah mapel terakhir (berdasarkan
     * urutan start_time) dalam satu ExamPeriod. Dipakai untuk menentukan
     * apakah Timer Sesi perlu ditampilkan (mengambil alih Timer Mapel).
     */
    private function computeIsFinalMapel(ExamSchedule $schedule, ExamPeriod $period): bool
    {
        if ($schedule->exam_period_id === null) {
            return true;
        }

        $lastSchedule = $period->schedules()
            ->orderBy('start_time', 'desc')
            ->first();

        return $lastSchedule !== null && $lastSchedule->id === $schedule->id;
    }

    /**
     * Simpan atau hapus jawaban peserta (jawaban kosong dihapus agar jumlah
     * "soal dijawab" akurat). Hanya soal milik mata pelajaran ujian yang
     * ditargetkan ke kelas peserta yang diterima.
     *
     * @param  array<mixed>  $answers
     */
    private function storeAnswers(ExamSession $session, ExamSchedule $schedule, array $answers, int $classroomId): void
    {
        $validQuestionIds = $schedule->subject->questions()
            ->where('is_active', true)
            ->targetingClassroom($classroomId)
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
            $doubtfulIds = ExamAnswer::query()
                ->where('exam_session_id', $session->id)
                ->whereIn('question_id', $toDelete)
                ->where('is_doubtful', true)
                ->pluck('question_id')
                ->all();

            ExamAnswer::query()
                ->where('exam_session_id', $session->id)
                ->whereIn('question_id', $toDelete)
                ->whereNotIn('question_id', $doubtfulIds)
                ->delete();

            if ($doubtfulIds !== []) {
                ExamAnswer::query()
                    ->where('exam_session_id', $session->id)
                    ->whereIn('question_id', $doubtfulIds)
                    ->update(['student_answer' => null]);
            }
        }
    }

    private function deny(string $message): RedirectResponse
    {
        return redirect()->route('peserta.dashboard')->with('error', $message);
    }
}
