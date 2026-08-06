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

        return response()->json([
            'violations' => $this->roomViolations($room, 5),
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
