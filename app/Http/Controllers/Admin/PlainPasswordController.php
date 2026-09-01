<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        abort_if(! in_array($user->role, [User::ROLE_PESERTA, User::ROLE_PENGAWAS, User::ROLE_GURU_MAPEL], true), 404);

        return response()->json(['plain_password' => $user->plain_password]);
    }
}
