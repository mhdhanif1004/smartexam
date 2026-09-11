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
        $rooms = $this->supervisorRooms();

        if ($rooms->isEmpty()) {
            return response()->json(['violations' => [], 'room_ids' => []]);
        }

        return response()->json([
            'violations' => $this->violationsForRooms($rooms, 5),
            'room_ids' => $rooms->pluck('id')->values()->all(),
        ]);
    }

    /**
     * Polling endpoint: kembalikan pelanggaran BARU yang belum dilihat client.
     * Client mengirim parameter `since` (ID pelanggaran terakhir yang diketahui)
     * dan hanya pelanggaran dengan id lebih besar yang dikembalikan.
     */
    public function polling(Request $request): JsonResponse
    {
        $rooms = $this->supervisorRooms();
        $since = (int) $request->query('since', 0);

        if ($rooms->isEmpty()) {
            return response()->json(['violations' => [], 'unhandled_count' => 0, 'room_ids' => []]);
        }

        $roomIds = $rooms->pluck('id')->all();

        $violations = Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
            ->whereHas('examSession.examSchedule', fn ($query) => $query->whereIn('room_id', $roomIds))
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->map(fn (Violation $v) => Violation::panelPayload($v, $v->id > $since))
            ->values();

        // Badge sekarang menghitung TOTAL belum ditangani di SEMUA ruangan
        // pengawas (tanpa limit 20) agar akurat; sebagian aman karena hanya
        // menghitung violations milik room_ids pengawas, bukan global.
        $unhandledCount = Violation::query()
            ->whereHas('examSession.examSchedule', fn ($query) => $query->whereIn('room_id', $roomIds))
            ->where('handled_by_supervisor', false)
            ->count();

        return response()->json([
            'violations' => $violations,
            'unhandled_count' => $unhandledCount,
            'room_ids' => $roomIds,
        ]);
    }

    /**
     * Tandai pelanggaran sudah ditangani pengawas. Aksi ini hanya menambah
     * penanda "sudah dilihat/ditangani" di UI pengawas; tidak mengubah
     * violation_flag di exam_sessions maupun data asli violations.
     */
    public function handle(Request $request, Violation $violation): JsonResponse
    {
        $rooms = $this->supervisorRooms();

        if ($rooms->isEmpty()) {
            return response()->json(['error' => 'Anda belum ditugaskan ke ruangan ujian mana pun.'], 403);
        }

        $roomIds = $rooms->pluck('id')->all();

        $owned = Violation::query()
            ->whereKey($violation->id)
            ->whereHas('examSession.examSchedule', fn ($query) => $query->whereIn('room_id', $roomIds))
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
