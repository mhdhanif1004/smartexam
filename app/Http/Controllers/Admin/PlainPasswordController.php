<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;

class PlainPasswordController extends Controller
{
    /**
     * Return the decrypted plain password of a participant, supervisor, or
     * guru mapel.
     *
     * Password tidak pernah ikut di-render di HTML index; hanya diambil
     * on-demand lewat endpoint ini ketika tombol mata diklik.
     */
    public function show(User $user): JsonResponse
    {
        abort_if(! in_array($user->role, [User::ROLE_PESERTA, User::ROLE_PENGAWAS, User::ROLE_GURU_MAPEL, User::ROLE_KEPALA_SEKOLAH], true), 404);

        ActivityLogger::log(
            action: ActivityAction::LIHAT_PLAIN_PASSWORD,
            subject: $user,
            description: "Melihat plain password milik {$user->name} ({$user->role})",
            properties: ['user_id' => $user->id, 'role' => $user->role],
        );

        return response()->json(['plain_password' => $user->plain_password]);
    }
}
