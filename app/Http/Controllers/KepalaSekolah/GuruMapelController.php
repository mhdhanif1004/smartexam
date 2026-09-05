<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuruMapelController extends Controller
{
    /**
     * Daftar guru mapel read-only untuk Kepala Sekolah.
     * Mendukung pencarian (Nama, Email, NIP).
     */
    public function index(Request $request): View
    {
        $guruMapels = GuruMapel::query()
            ->with(['user', 'subjects'])
            ->withCount('assignments')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderBy('user_id')
            ->paginate(10)
            ->withQueryString();

        return view('kepala_sekolah.guru-mapels.index', compact('guruMapels'));
    }
}
