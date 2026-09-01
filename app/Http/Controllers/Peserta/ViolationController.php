<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    /**
     * Terima laporan pelanggaran otomatis dari mesin deteksi peserta.
     * Mencatat pelanggaran, mengaktifkan slot checklist berikutnya, lalu
     * menonaktifkan absensi peserta agar sesi ujian dihentikan otomatis.
     *
     * Pengecualian: keluar dari mode layar penuh hanya dicatat. Peserta
     * diharapkan kembali ke layar penuh lewat modal blocking, sehingga sesi
     * tidak dinonaktifkan dan tidak memerlukan absensi ulang pengawas.
     */
    public function store(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $student = auth()->user()?->student;
        if (! $student instanceof Student) {
            return response()->json(['error' => 'Akun ini tidak terdaftar sebagai peserta.'], 403);
        }

        $schedule = ExamSchedule::query()
            ->with('subject')
            ->find($schedule->id);

        if ($schedule === null || ! $student->isAssignedToSchedule($schedule)) {
            return response()->json(['error' => 'Anda tidak memiliki akses ke ujian tersebut.'], 403);
        }

        $session = ExamSession::query()->firstOrCreate(
            ['student_id' => $student->id, 'exam_schedule_id' => $schedule->id],
            ['status' => ExamSession::STATUS_NOT_STARTED],
        );

        if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->started_at === null) {
            return response()->json(['error' => 'Sesi ujian belum dimulai.'], 403);
        }

        if ($schedule->isExpiredAfterGrace($session)) {
            return response()->json(['expired' => true], 422);
        }

        $type = $request->string('violation_type')->toString();
        if (! array_key_exists($type, Violation::AUTO_TYPES)) {
            $type = Violation::TYPE_TAB_SWITCH;
        }

        Violation::create([
            'exam_session_id' => $session->id,
            'violation_type' => $type,
            'occurred_at' => now(),
            'reported_by' => null,
        ]);

        if ($type === Violation::TYPE_FULLSCREEN_EXIT) {
            return response()->json(['recorded' => true]);
        }

        $flagCountBefore = (int) $session->activeViolationFlags();

        $session->activateNextViolationFlag();
        $session->update(['attendance_confirmed' => false]);

        // Hentikan paksa otomatis pada pelanggaran ke-4: ketiga slot checklist
        // sudah penuh dan masih ada satu pelanggaran lagi yang masuk.
        // `locked_by_admin_by` dibiarkan null untuk menandai kunci berasal dari
        // sistem, bukan admin. Admin tetap dapat membuka kembali lewat panel
        // Riwayat Pelanggaran.
        if ($flagCountBefore >= 3) {
            $session->update([
                'locked_by_admin' => true,
                'locked_by_admin_at' => now(),
                'locked_by_admin_by' => null,
            ]);
        }

        return response()->json([
            'redirect' => true,
            'url' => route('peserta.dashboard'),
            'message' => 'Terdeteksi aktivitas mencurigakan. Anda akan diarahkan kembali ke dashboard.',
        ]);
    }
}
