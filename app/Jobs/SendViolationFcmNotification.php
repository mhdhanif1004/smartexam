<?php

namespace App\Jobs;

use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Violation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendViolationFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $violationId) {}

    public function handle(Messaging $messaging): void
    {
        $violation = Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room', 'examSession.examSchedule.examPeriod'])
            ->find($this->violationId);

        if ($violation === null) {
            return;
        }

        $schedule = $violation->examSession?->examSchedule;
        if ($schedule === null) {
            return;
        }

        $roomId = (int) ($schedule->room_id ?? 0);
        if ($roomId <= 0) {
            return;
        }

        $examDate = $schedule->exam_date?->format('Y-m-d') ?? now()->toDateString();

        // Kumpulkan user pengawas yang bertugas di ruangan ini pada tanggal ujian.
        // Prioritas: rotasi hari itu; bila tidak ada rotasi, fallback ke penempatan statis supervisors.room_id.
        $rotationSupervisorIds = SupervisorRoomAssignment::query()
            ->where('room_id', $roomId)
            ->where('exam_date', $examDate)
            ->pluck('supervisor_id');

        if ($rotationSupervisorIds->isNotEmpty()) {
            $supervisorUserIds = Supervisor::query()
                ->whereIn('id', $rotationSupervisorIds)
                ->pluck('user_id');
        } else {
            $supervisorUserIds = Supervisor::query()
                ->where('room_id', $roomId)
                ->pluck('user_id');
        }

        // Admin juga menerima notifikasi pelanggaran (global, seperti polling admin).
        $adminUserIds = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('id');

        $recipientUserIds = $supervisorUserIds->merge($adminUserIds)->unique()->values();

        if ($recipientUserIds->isEmpty()) {
            return;
        }

        $tokens = UserFcmToken::query()
            ->whereIn('user_id', $recipientUserIds)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $studentName = $violation->examSession?->student?->user?->name ?? '-';
        $subjectName = $schedule->subject?->name ?? '-';
        $roomName = $schedule->room?->display_name ?? ('Ruang #'.$roomId);
        $violationLabel = Violation::typeLabel($violation->violation_type);

        $title = 'Pelanggaran Ujian Terdeteksi';
        $body = sprintf('%s (%s) — %s di %s', $studentName, $subjectName, $violationLabel, $roomName);

        $data = [
            'violation_id' => (string) $violation->id,
            'violation_type' => (string) $violation->violation_type,
            'student_name' => (string) $studentName,
            'subject' => (string) $subjectName,
            'room_name' => (string) $roomName,
            'room_id' => (string) $roomId,
            'exam_schedule_id' => (string) $schedule->id,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            /** @var \Kreait\Firebase\Messaging\MulticastSendReport $report */
            $report = $messaging->sendMulticast($message, $tokens);

            $toDelete = array_merge($report->unknownTokens(), $report->invalidTokens());
            if ($toDelete !== []) {
                UserFcmToken::query()->whereIn('token', $toDelete)->delete();
                Log::info('FCM: hapus token tidak valid', ['tokens' => $toDelete, 'violation_id' => $violation->id]);
            }

            if ($report->hasFailures()) {
                Log::warning('FCM: sebagian pengiriman gagal', [
                    'violation_id' => $violation->id,
                    'failures' => $report->failures()->count(),
                    'total' => $report->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('FCM: gagal kirim notifikasi pelanggaran', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
            ]);

            // Lempar kembali agar queue retry (tries=3) dapat berjalan untuk error transient.
            throw $e;
        }
    }
}
