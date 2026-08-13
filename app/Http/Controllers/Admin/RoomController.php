<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Models\Room;
use App\Models\Supervisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::query()
            ->withCount([
                'supervisors',
                'students',
                'examSchedules',
                'roomAssignments as assigned_students_count' => fn ($query) => $query->selectRaw('COUNT(DISTINCT student_id)'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.rooms.index', compact('rooms'));
    }

    public function create(): View
    {
        return view('admin.rooms.create', $this->formData());
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $supervisorIds = $this->supervisorIds($request->input('supervisor_ids'));

        $room = DB::transaction(function () use ($validated, $supervisorIds) {
            $room = Room::create([
                'name' => $validated['name'],
                'capacity' => (int) ($validated['capacity'] ?? 0),
            ]);

            $this->assignSupervisors($supervisorIds, $room);

            return $room;
        });

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$room->name} berhasil ditambahkan.");
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', $this->formData($room));
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $validated = $request->validated();
        $supervisorIds = $this->supervisorIds($request->input('supervisor_ids'));

        DB::transaction(function () use ($validated, $supervisorIds, $room) {
            $room->update([
                'name' => $validated['name'],
                'capacity' => (int) ($validated['capacity'] ?? 0),
            ]);

            $this->assignSupervisors($supervisorIds, $room);
        });

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$room->name} berhasil diperbarui.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        $name = $room->name;
        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$name} berhasil dihapus.");
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu ruangan untuk dihapus.');
        }

        $deleted = 0;
        $blocked = [];

        DB::transaction(function () use ($ids, &$deleted, &$blocked) {
            $rooms = Room::query()
                ->withCount('examSchedules')
                ->whereIn('id', $ids)
                ->get();

            foreach ($rooms as $room) {
                if ($room->exam_schedules_count > 0) {
                    $blocked[] = $room->name;

                    continue;
                }

                $room->delete();
                $deleted++;
            }
        });

        $flash = [];

        if ($deleted > 0) {
            $flash['success'] = "{$deleted} ruangan berhasil dihapus.";
        }

        if ($blocked !== []) {
            $names = implode(', ', $blocked);
            $flash['error'] = "Ruangan {$names} tidak dapat dihapus karena masih memiliki jadwal ujian. Hapus jadwalnya terlebih dahulu.";
        }

        if ($flash === []) {
            $flash['error'] = 'Ruangan yang dipilih tidak ditemukan.';
        }

        return back()->with($flash);
    }

    /**
     * @param  array<mixed>|null  $input
     * @return list<int>
     */
    private function supervisorIds(mixed $input): array
    {
        return array_values(array_unique(array_map('intval', (array) $input)));
    }

    /**
     * Terapkan daftar pengawas untuk satu ruangan (bisa lebih dari satu).
     * Pengawas ruangan ini yang tidak ada lagi di daftar dilepas (room_id
     * null). Pengawas yang ada di daftar diarahkan ke ruangan ini; karena
     * supervisors.room_id hanya satu kolom, pengawas yang bertugas di ruangan
     * lain otomatis pindah ke ruangan yang terakhir disubmit.
     *
     * @param  list<int>  $supervisorIds
     */
    private function assignSupervisors(array $supervisorIds, Room $room): void
    {
        if ($supervisorIds === []) {
            Supervisor::query()->where('room_id', $room->id)->update(['room_id' => null]);

            return;
        }

        Supervisor::query()
            ->where('room_id', $room->id)
            ->whereNotIn('id', $supervisorIds)
            ->update(['room_id' => null]);

        Supervisor::query()
            ->whereIn('id', $supervisorIds)
            ->update(['room_id' => $room->id]);
    }

    /**
     * Data bersama untuk halaman Tambah/Edit Ruangan.
     *
     * @return array<string, mixed>
     */
    private function formData(?Room $room = null): array
    {
        $supervisors = Supervisor::query()->with(['user', 'room'])->orderBy('user_id')->get();

        $currentSupervisorIds = $room !== null ? $room->supervisors()->pluck('id')->all() : [];

        return compact('room', 'supervisors', 'currentSupervisorIds');
    }
}
