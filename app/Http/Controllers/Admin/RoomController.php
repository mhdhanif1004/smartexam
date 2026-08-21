<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Models\ExamRoomAssignment;
use App\Models\Room;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today()->toDateString();

        $rooms = Room::query()
            ->withCount([
                'students',
                'examSchedules',
                'roomAssignments as assigned_students_count' => fn ($query) => $query->selectRaw('COUNT(DISTINCT student_id)'),
            ])
            ->withCount([
                'supervisorRoomAssignments as today_supervisors_count' => fn ($query) => $query->where('exam_date', $today),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();

                if (preg_match('/^\d+$/', (string) $search)) {
                    $query->whereRaw('CAST(room_number AS CHAR) LIKE ?', [(string) $search.'%']);
                } else {
                    $query->whereRaw('0 = 1');
                }
            })
            ->orderBy('room_number')
            ->paginate(10)
            ->withQueryString();

        return view('admin.rooms.index', compact('rooms'));
    }

    public function detail(Room $room): View
    {
        $assignments = ExamRoomAssignment::query()
            ->with(['examPeriod', 'student.user'])
            ->where('room_id', $room->id)
            ->orderBy('exam_period_id')
            ->orderBy('seat_number')
            ->get();

        $assignmentsByPeriod = $assignments
            ->groupBy(fn (ExamRoomAssignment $assignment) => $assignment->exam_period_id)
            ->sortBy(fn (Collection $group) => $group->first()?->examPeriod?->exam_date.'|'.$group->first()?->examPeriod?->start_time)
            ->values();

        $supervisorAssignments = SupervisorRoomAssignment::query()
            ->with(['examPeriod', 'supervisor.user'])
            ->where('room_id', $room->id)
            ->orderBy('exam_date')
            ->orderBy('exam_period_id')
            ->get();

        $supervisorAssignmentsByDate = $supervisorAssignments
            ->groupBy(fn (SupervisorRoomAssignment $a) => $a->exam_date->toDateString())
            ->map(function (Collection $dateGroup, string $date) {
                $sessions = $dateGroup
                    ->groupBy('exam_period_id')
                    ->map(function (Collection $periodGroup) {
                        $period = $periodGroup->first()?->examPeriod;
                        $supervisorNames = $periodGroup
                            ->map(fn (SupervisorRoomAssignment $a) => $a->supervisor?->user?->name)
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        return [
                            'period' => $period,
                            'supervisor_names' => $supervisorNames,
                        ];
                    })
                    ->sortBy(fn ($s) => $s['period']?->start_time ?? '')
                    ->values();

                $uniqueSupervisors = $dateGroup
                    ->map(fn (SupervisorRoomAssignment $a) => $a->supervisor_id)
                    ->unique()
                    ->count();

                return [
                    'date' => Carbon::parse($date),
                    'sessions' => $sessions,
                    'session_count' => $sessions->count(),
                    'supervisor_count' => $uniqueSupervisors,
                ];
            })
            ->values();

        $totalSupervisors = $supervisorAssignments
            ->pluck('supervisor_id')
            ->unique()
            ->count();

        $studentDates = $assignments->pluck('examPeriod.exam_date')->filter()->unique()->pluck('value');
        $supervisorDates = $supervisorAssignments->pluck('exam_date')->filter()->unique()->pluck('value');
        $totalDays = $studentDates->concat($supervisorDates)->unique()->count();

        return view('admin.rooms.detail', [
            'room' => $room,
            'assignmentsByPeriod' => $assignmentsByPeriod,
            'totalSessions' => $assignmentsByPeriod->count(),
            'totalStudents' => $assignments->unique('student_id')->count(),
            'supervisorAssignmentsByDate' => $supervisorAssignmentsByDate,
            'totalSupervisors' => $totalSupervisors,
            'totalDays' => $totalDays,
        ]);
    }

    public function create(): View
    {
        $nextRoomNumber = (int) (Room::max('room_number') ?? 0) + 1;

        return view('admin.rooms.create', compact('nextRoomNumber'));
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $room = Room::create([
            'room_number' => (int) $validated['room_number'],
            'capacity' => (int) $validated['capacity'],
            'supervisor_count' => (int) $validated['supervisor_count'],
        ]);

        if ($request->has('assign_supervisor_ids')) {
            Supervisor::whereIn('id', $request->input('assign_supervisor_ids'))
                ->whereNull('room_id')
                ->update(['room_id' => $room->id]);
        }

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$room->display_name} berhasil ditambahkan.");
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', compact('room'));
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $validated = $request->validated();
        $oldSupervisorCount = $room->supervisor_count;
        $newSupervisorCount = (int) $validated['supervisor_count'];

        $room->update([
            'room_number' => (int) $validated['room_number'],
            'capacity' => (int) $validated['capacity'],
            'supervisor_count' => $newSupervisorCount,
        ]);

        $assignedIds = $request->input('assign_supervisor_ids', []);
        Supervisor::where('room_id', $room->id)
            ->whereNotIn('id', $assignedIds)
            ->update(['room_id' => null]);

        if ($request->has('assign_supervisor_ids')) {
            Supervisor::whereIn('id', $request->input('assign_supervisor_ids'))
                ->whereNull('room_id')
                ->update(['room_id' => $room->id]);
        }

        $warning = null;

        if ($oldSupervisorCount !== $newSupervisorCount) {
            $hasFutureRotation = SupervisorRoomAssignment::query()
                ->where('room_id', $room->id)
                ->whereHas('examPeriod', fn ($q) => $q->where('exam_date', '>=', Carbon::today()->toDateString()))
                ->exists();

            if ($hasFutureRotation) {
                $warning = "Ruangan {$room->display_name} sudah punya rotasi pengawas untuk periode yang akan datang. Mengubah jumlah maksimal pengawas TIDAK otomatis memperbarui rotasi yang sudah ada — silakan generate ulang rotasi secara manual dari halaman Periode Ujian jika diperlukan.";
            }
        }

        if ($warning !== null) {
            return redirect()->route('admin.rooms.index')->with('warning', $warning);
        }

        return redirect()->route('admin.rooms.index')->with('success', "Ruangan {$room->display_name} berhasil diperbarui.");
    }

    public function destroy(Room $room): RedirectResponse
    {
        $name = $room->display_name;
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
                    $blocked[] = $room->display_name;

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
}
