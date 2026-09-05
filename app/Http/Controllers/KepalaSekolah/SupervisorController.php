<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    /**
     * Daftar pengawas read-only untuk Kepala Sekolah.
     * Mendukung pencarian (Nama, Username, Nomor Ruangan) dan filter ruangan.
     */
    public function index(Request $request): View
    {
        $supervisors = Supervisor::query()
            ->with(['user', 'room'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($builder) use ($search) {
                    $builder->whereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })->orWhereHas('room', function ($room) use ($search) {
                        $room->where('room_number', 'like', "%{$search}%");
                    });
                });
            })
            ->when($request->filled('room'), fn ($query) => $query->where('room_id', $request->integer('room')))
            ->orderBy('user_id')
            ->paginate(10)
            ->withQueryString();

        $rooms = Room::query()->orderBy('room_number')->get();

        return view('kepala_sekolah.supervisors.index', compact('supervisors', 'rooms'));
    }
}
