<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    /**
     * Simpan / perbarui token FCM perangkat milik user yang sedang login.
     * Token bersifat unik global — bila sudah ada milik user lain maka akan
     * dipindahkan kepemilikan ke user saat ini (re-login di device sama).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'device_type' => ['nullable', 'string', 'in:web,android,ios'],
        ]);

        UserFcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Hapus token FCM (mis. saat logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        UserFcmToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
