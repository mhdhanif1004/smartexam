<?php

namespace App\Http\Middleware;

use App\Models\ExamPeriod;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi mode "pengawas mandiri" untuk Guru Mapel.
 *
 * Gate: user ber-role guru_mapel DAN dia adalah pembuat ExamPeriod tersebut
 * (created_by_user_id === user.id). JALUR TERPISAH dari otorisasi pengawas
 * asli (role:pengawas + supervisor_room_assignments) — guru tidak boleh
 * "meminjam" jalur pengawas, dan hanya boleh mengawasi sesi buatannya sendiri.
 *
 * Dipasang per-route pada parameter {examPeriod} (model binding).
 */
class EnsureOwnsExamPeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $period = $request->route('examPeriod') ?? $request->route('exam_period');

        abort_unless($user !== null, 401);

        // Defense-in-depth: role gate juga dipasang di route group, tapi tetap
        // diperiksa di sini supaya middleware ini aman dipakai di mana saja.
        abort_unless($user->isGuruMapel(), 403, 'Anda bukan guru mapel.');

        abort_unless($period instanceof ExamPeriod, 404, 'Sesi ujian tidak ditemukan.');

        abort_unless(
            $period->created_by_user_id !== null && $period->created_by_user_id === $user->id,
            403,
            'Anda bukan pembuat sesi ujian ini. Hanya pembuat yang dapat mengawasi sesi ini.'
        );

        return $next($request);
    }
}
