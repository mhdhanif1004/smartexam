<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        ActivityLogger::log(
            action: ActivityAction::PERBARUI_PROFIL,
            subject: $request->user(),
            description: 'Memperbarui profil ('.$request->user()->email.')',
            properties: ['user_id' => $request->user()->id, 'email' => $request->user()->email],
        );

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $deletedId = $user->id;
        $deletedEmail = $user->email;

        // Log sebelum logout/delete — causer masih tersedia.
        ActivityLogger::log(
            action: ActivityAction::HAPUS_AKUN,
            subject: $user,
            description: 'Menghapus akun ('.$deletedEmail.')',
            properties: ['user_id' => $deletedId, 'email' => $deletedEmail],
        );

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
