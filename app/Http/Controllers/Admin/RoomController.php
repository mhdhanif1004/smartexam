<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::query()
            ->withCount(['supervisors', 'students', 'examSchedules'])
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
        $studentIds = $this->studentIds($request->input('student_ids'));

        $room = DB::transaction(function () use ($validated, $studentIds) {
            $this->assertStudentsAllowed($studentIds, null);

            $room = Room::create([
                'name' => $validated['name'],
                'capacity' => count($studentIds),
            ]);

            if ($studentIds !== []) {
                Student::query()->whereIn('id', $studentIds)->update(['room_id' => $room->id]);
            }

            $this->assignSupervisor($validated['supervisor_id'] ?? null, $room);

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
        $studentIds = $this->studentIds($request->input('student_ids'));

        DB::transaction(function () use ($validated, $studentIds, $room) {
            $this->assertStudentsAllowed($studentIds, $room);

            $room->update([
                'name' => $validated['name'],
                'capacity' => count($studentIds),
            ]);

            // Siswa yang baru dicentang (masih bebas) dipindah ke ruangan ini.
            if ($studentIds !== []) {
                Student::query()
                    ->whereIn('id', $studentIds)
                    ->whereNull('room_id')
                    ->update(['room_id' => $room->id]);
            }

            // Siswa yang tadinya di ruangan ini tetapi tidak dicentang lagi
            // dilepas (room_id = null) sehingga tersedia untuk ruangan lain.
            Student::query()
                ->where('room_id', $room->id)
                ->whereNotIn('id', $studentIds)
                ->update(['room_id' => null]);

            $this->assignSupervisor($validated['supervisor_id'] ?? null, $room);
        });

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$room->name} berhasil diperbarui.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        $name = $room->name;
        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$name} berhasil dihapus.");
    }

    /**
     * @param  array<mixed>|null  $input
     * @return list<int>
     */
    private function studentIds(mixed $input): array
    {
        return array_values(array_unique(array_map('intval', (array) $input)));
    }

    /**
     * Pastikan setiap siswa yang dicentang memang boleh ditempatkan di ruangan
     * ini: belum punya ruangan sama sekali (create/edit) atau sudah berada di
     * ruangan ini (edit).
     *
     * @param  list<int>  $studentIds
     */
    private function assertStudentsAllowed(array $studentIds, ?Room $room): void
    {
        if ($studentIds === []) {
            return;
        }

        $allowed = Student::query()
            ->when($room !== null, fn ($query) => $query
                ->where(function ($builder) use ($room) {
                    $builder->whereNull('room_id')->orWhere('room_id', $room->id);
                }))
            ->when($room === null, fn ($query) => $query->whereNull('room_id'))
            ->pluck('id')
            ->all();

        $allowedSet = array_flip($allowed);

        foreach ($studentIds as $studentId) {
            if (! isset($allowedSet[$studentId])) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Terdapat siswa yang sudah ditempatkan di ruangan lain. Halaman sudah dimuat ulang, silakan periksa daftar terbaru.',
                ]);
            }
        }
    }

    /**
     * Terapkan pengawas untuk satu ruangan (satu pengawas per ruangan).
     * Pengawas yang sedang bertugas di ruangan lain otomatis dipindahkan;
     * pengawas lama ruangan ini dilepas bila ada pengawas baru dipilih.
     */
    private function assignSupervisor(?int $supervisorId, Room $room): void
    {
        $current = Supervisor::query()->where('room_id', $room->id)->first();

        if ($supervisorId === null || $supervisorId === 0) {
            if ($current !== null) {
                $current->update(['room_id' => null]);
            }

            return;
        }

        $supervisor = Supervisor::findOrFail($supervisorId);

        if ($current !== null && $current->id === $supervisor->id) {
            return;
        }

        if ($current !== null) {
            $current->update(['room_id' => null]);
        }

        $supervisor->update(['room_id' => $room->id]);
    }

    /**
     * Data bersama untuk halaman Tambah/Edit Ruangan.
     *
     * @return array<string, mixed>
     */
    private function formData(?Room $room = null): array
    {
        $assigned = $room !== null
            ? $room->students()->with('user')->orderBy('class_name')->orderBy('nisn')->get()
            : collect();

        $available = Student::query()
            ->with('user')
            ->whereNull('room_id')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->get();

        $classes = $available->pluck('class_name')
            ->merge($assigned->pluck('class_name'))
            ->unique()
            ->sort()
            ->values();

        $supervisors = Supervisor::query()->with(['user', 'room'])->orderBy('user_id')->get();

        $currentSupervisorId = $room !== null ? $room->supervisors()->value('id') : null;

        return compact('room', 'assigned', 'available', 'classes', 'supervisors', 'currentSupervisorId');
    }
}
