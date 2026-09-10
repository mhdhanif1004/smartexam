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
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\SendReport;

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
            Log::warning('FCM DISPATCH: tidak ada penerima', ['violation_id' => $violation->id, 'room_id' => $roomId, 'exam_date' => $examDate]);

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

        // Data HARUS memuat type=violation agar Flutter _showFromMessage memilih pelanggaran_channel (high+sound).
        // Tambahkan title/body juga agar foreground/background handler tidak fallback ke 'Ada aktivitas baru.'
        $data = [
            'type' => 'violation',
            'title' => $title,
            'body' => $body,
            'violation_id' => (string) $violation->id,
            'violation_type' => (string) $violation->violation_type,
            'student_name' => (string) $studentName,
            'subject' => (string) $subjectName,
            'room_name' => (string) $roomName,
            'room_id' => (string) $roomId,
            'exam_schedule_id' => (string) $schedule->id,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        // Bagian A: Android-specific agar masuk pelanggaran_channel (high+sound+vibrate), bukan fallback channel tanpa suara.
        // channel_id HARUS persis sama dengan NotificationService.pelanggaranChannel.id = 'pelanggaran_channel'.
        // tag dibuat UNIK per violation_id agar Android TIDAK collapse/silent-update notifikasi sebelumnya (Bagian B).
        $androidConfig = AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'channel_id' => 'pelanggaran_channel',
                'sound' => 'default',
                'visibility' => 'PUBLIC',
                'notification_priority' => 'PRIORITY_MAX',
                // tag unik → setiap pelanggaran jadi notifikasi terpisah dengan alert/suara, bukan replace diam-diam
                'tag' => 'violation-'.$violation->id,
            ],
        ]);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withAndroidConfig($androidConfig);

        // Bagian B: logging eksplisit per percobaan agar 3 percobaan dapat dibedakan jelas di log
        Log::warning('FCM DISPATCH', [
            'violation_id' => $violation->id,
            'timestamp' => now()->toIsoString(),
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : 1,
            'token_count' => count($tokens),
            'recipient_user_ids' => $recipientUserIds->all(),
            'tokens_preview' => array_map(fn ($t) => substr($t, 0, 16).'...len='.strlen($t), $tokens),
        ]);

        try {
            /** @var MulticastSendReport $report */
            $report = $messaging->sendMulticast($message, $tokens);

            $successCount = $report->successes()->count();
            $failureCount = $report->failures()->count();
            $toDelete = array_merge($report->unknownTokens(), $report->invalidTokens());

            // Detail per-token agar tahu token mana gagal dan kenapa
            $itemsDetail = array_map(function ($item) {
                /** @var SendReport $item */
                $err = $item->error();

                return [
                    'token_preview' => substr($item->target()->value(), 0, 16).'...',
                    'success' => $item->isSuccess(),
                    'error' => $err ? $err->getMessage() : null,
                ];
            }, $report->getItems());

            Log::warning('FCM RESULT', [
                'violation_id' => $violation->id,
                'timestamp' => now()->toIsoString(),
                'total' => $report->count(),
                'successCount' => $successCount,
                'failureCount' => $failureCount,
                'toDelete_count' => count($toDelete),
                'items' => $itemsDetail,
            ]);

            if ($toDelete !== []) {
                UserFcmToken::query()->whereIn('token', $toDelete)->delete();
                Log::warning('FCM: hapus token tidak valid', ['tokens' => $toDelete, 'violation_id' => $violation->id]);
            }

            if ($failureCount > 0) {
                Log::warning('FCM: sebagian pengiriman gagal', [
                    'violation_id' => $violation->id,
                    'failures' => $failureCount,
                    'total' => $report->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('FCM: gagal kirim notifikasi pelanggaran', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);

            // Lempar kembali agar queue retry (tries=3) dapat berjalan untuk error transient.
            throw $e;
        }
    }
}
