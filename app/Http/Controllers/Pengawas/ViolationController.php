<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Traits\ScopesSupervisorRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    use ScopesSupervisorRoom;

    /**
     * Daftar pelanggaran terbaru di ruangan pengawas (untuk polling panel
     * "Notifikasi Pelanggaran" di dashboard).
     */
    public function recent(): JsonResponse
    {
        $room = $this->supervisorRoom();

        if ($room === null) {
            return response()->json(['violations' => []]);
        }

        return response()->json([
            'violations' => $this->roomViolations($room, 5),
        ]);
    }

    /**
     * Polling endpoint: kembalikan pelanggaran BARU yang belum dilihat client.
     * Client mengirim parameter `since` (ID pelanggaran terakhir yang diketahui)
     * dan hanya pelanggaran dengan id lebih besar yang dikembalikan.
     */
    public function polling(Request $request): JsonResponse
    {
        $room = $this->supervisorRoom();
        $since = (int) $request->query('since', 0);

        if ($room === null) {
            return response()->json(['violations' => [], 'unhandled_count' => 0]);
        }

        $violations = Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
            ->whereHas('examSession.examSchedule', fn ($query) => $query->where('room_id', $room->id))
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->map(fn (Violation $v) => Violation::panelPayload($v, $v->id > $since))
            ->values();

        // Badge bersumber dari jumlah item BELUM ditangani dalam daftar yang
        // ditampilkan — konsisten dengan panel, tidak pernah >0 saat daftar kosong.
        $unhandledCount = $violations->where('handled', false)->count();

        return response()->json([
            'violations' => $violations,
            'unhandled_count' => $unhandledCount,
        ]);
    }

    /**
     * Tandai pelanggaran sudah ditangani pengawas. Aksi ini hanya menambah
     * penanda "sudah dilihat/ditangani" di UI pengawas; tidak mengubah
     * violation_flag di exam_sessions maupun data asli violations.
     */
    public function handle(Request $request, Violation $violation): JsonResponse
    {
        $room = $this->supervisorRoom();

        if ($room === null) {
            return response()->json(['error' => 'Anda belum ditugaskan ke ruangan ujian mana pun.'], 403);
        }

        $owned = Violation::query()
            ->whereKey($violation->id)
            ->whereHas('examSession.examSchedule', fn ($query) => $query->where('room_id', $room->id))
            ->exists();

        if (! $owned) {
            return response()->json(['error' => 'Pelanggaran tidak berada di ruangan Anda.'], 403);
        }

        $violation->update([
            'handled_by_supervisor' => true,
            'handled_at' => now(),
            'handled_by' => auth()->id(),
        ]);

        return response()->json(['ok' => true, 'handled' => true]);
    }
}
