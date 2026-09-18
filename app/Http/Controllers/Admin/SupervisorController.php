<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupervisorRequest;
use App\Http\Requests\Admin\UpdateSupervisorRequest;
use App\Models\Room;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupervisorController extends Controller
{
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

        return view('admin.supervisors.index', compact('supervisors', 'rooms'));
    }

    public function create(): View
    {
        return view('admin.supervisors.create');
    }

    public function show(Supervisor $supervisor): View
    {
        $supervisor->load([
            'user',
            'room',
            'roomAssignments.examPeriod',
            'roomAssignments.room',
        ]);

        $dayNames = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $assignmentsByDate = $supervisor->roomAssignments
            ->sortByDesc(fn ($assignment) => $assignment->exam_date?->toDateString() ?? '0000-00-00')
            ->groupBy(fn ($assignment) => $assignment->exam_date?->toDateString() ?? '0000-00-00')
            ->map(function ($group, string $date) use ($dayNames): array {
                $dateObj = $group->first()->exam_date;

                return [
                    'date' => $dateObj,
                    'dateLabel' => $dateObj->format('d M Y'),
                    'dayLabel' => $dayNames[$dateObj->format('l')] ?? $dateObj->format('l'),
                    'assignments' => $group,
                ];
            })
            ->values();

        return view('admin.supervisors.show', compact('supervisor', 'assignmentsByDate'));
    }

    public function store(StoreSupervisorRequest $request): RedirectResponse
    {
        $createdUser = null;
        $createdSupervisor = null;

        DB::transaction(function () use ($request, &$createdUser, &$createdSupervisor) {
            $generator = app(CredentialGenerator::class);

            $password = $request->filled('password')
                ? $request->password
                : $generator->password();

            $createdUser = User::create([
                'name' => $request->name,
                'username' => $generator->username(),
                'password' => $password,
                'role' => User::ROLE_PENGAWAS,
                'is_active' => $request->boolean('is_active'),
            ]);
            $createdUser->forceFill(['plain_password' => $password])->save();

            // Pengawas baru dibuat tanpa ruangan (room_id null). Penugasan
            // ruangan hanya dilakukan lewat halaman Tambah/Edit Ruangan.
            $createdSupervisor = $createdUser->supervisor()->create();
        });

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_PENGAWAS,
            subject: $createdSupervisor ?? $createdUser,
            description: 'Menambahkan pengawas: '.($createdUser->name ?? $request->name),
            properties: ['user_id' => $createdUser?->id, 'supervisor_id' => $createdSupervisor?->id, 'nama' => $createdUser?->name ?? $request->name],
        );

        return redirect()->route('admin.supervisors.index')->with('success', 'Data pengawas berhasil ditambahkan.');
    }

    public function edit(Supervisor $supervisor): View
    {
        return view('admin.supervisors.edit', compact('supervisor'));
    }

    public function update(UpdateSupervisorRequest $request, Supervisor $supervisor): RedirectResponse
    {
        $oldName = $supervisor->user?->name;
        $oldIsActive = (bool) $supervisor->user?->is_active;

        $userData = [
            'name' => $request->name,
            'is_active' => $request->boolean('is_active'),
        ];

        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $userData['password'] = $request->password;
        }

        $supervisor->user->update($userData);

        if ($passwordChanged) {
            $supervisor->user->forceFill(['plain_password' => $request->password])->save();
        }

        // room_id sengaja TIDAK diubah di sini. Penugasan ruangan hanya
        // dikelola lewat halaman Tambah/Edit Ruangan, jadi pengawas yang sudah
        // punya ruangan tetap di ruangannya meskipun akunnya diedit.

        ActivityLogger::log(
            action: ActivityAction::UBAH_PENGAWAS,
            subject: $supervisor,
            description: 'Mengubah pengawas: '.($request->name ?? $oldName ?? 'ID '.$supervisor->id),
            properties: ['supervisor_id' => $supervisor->id, 'user_id' => $supervisor->user?->id, 'nama_lama' => $oldName, 'nama_baru' => $request->name, 'is_active_lama' => $oldIsActive, 'is_active_baru' => $request->boolean('is_active')],
        );

        return redirect()->route('admin.supervisors.index')->with('success', 'Data pengawas berhasil diperbarui.');
    }

    public function destroy(Supervisor $supervisor): RedirectResponse
    {
        $snapshotName = $supervisor->user?->name ?? 'ID '.$supervisor->id;
        $snapshotId = $supervisor->id;
        $snapshotUserId = $supervisor->user?->id;

        DB::transaction(function () use ($supervisor) {
            $supervisor->delete();
            $supervisor->user?->delete();
        });

        ActivityLogger::log(
            action: ActivityAction::HAPUS_PENGAWAS,
            description: "Menghapus pengawas {$snapshotName}",
            properties: ['supervisor_id' => $snapshotId, 'user_id' => $snapshotUserId, 'nama' => $snapshotName],
        );

        return redirect()->route('admin.supervisors.index')->with('success', 'Data pengawas berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu pengawas untuk dihapus.');
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $supervisors = Supervisor::query()->whereIn('id', $ids)->get();

            foreach ($supervisors as $supervisor) {
                $supervisor->delete();
                $supervisor->user?->delete();
                $deleted++;
            }
        });

        ActivityLogger::log(
            action: ActivityAction::HAPUS_BULK_PENGAWAS,
            description: "Menghapus {$deleted} pengawas sekaligus",
            properties: ['jumlah' => $deleted, 'ids' => $ids->values()->all()],
        );

        return back()->with('success', "{$deleted} data pengawas berhasil dihapus.");
    }
}
