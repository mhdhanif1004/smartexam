<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamPeriodAutoGenerateRequest;
use App\Http\Requests\Admin\StoreExamPeriodGroupsRequest;
use App\Http\Requests\Admin\StoreExamPeriodRequest;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamPeriodController extends Controller
{
    public function index(): View
    {
        $periods = ExamPeriod::query()
            ->withCount('schedules')
            ->orderBy('exam_date', 'desc')
            ->orderBy('start_time')
            ->get();

        return view('admin.exam-periods.index', compact('periods'));
    }

    public function create(): View
    {
        return view('admin.exam-periods.create');
    }

    public function store(StoreExamPeriodRequest $request): RedirectResponse
    {
        ExamPeriod::create([
            'name' => $request->name,
            'exam_date' => $request->exam_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return redirect()->route('admin.exam-periods.index')->with('success', 'Sesi ujian berhasil ditambahkan.');
    }

    public function autoGenerateCreate(): View
    {
        return view('admin.exam-periods.auto-generate-create', [
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('room_number')->get(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    /**
     * Generate otomatis: hitung jumlah sesi (gelombang) yang dibutuhkan sampai
     * semua siswa terpilih habis ditempatkan, lalu buat semua ExamPeriod,
     * ExamSchedule (ruangan × mapel, back-to-back) dan exam_room_assignments
     * dalam satu transaksi all-or-nothing.
     */
    public function autoGenerateStore(StoreExamPeriodAutoGenerateRequest $request): RedirectResponse
    {
        $roomIds = array_map('intval', $request->input('rooms'));
        $subjectRows = collect($request->input('subjects'))
            ->map(fn ($row) => [
                'subject_id' => (int) $row['subject_id'],
                'duration_minutes' => (int) $row['duration_minutes'],
            ])
            ->values()
            ->all();
        $classNames = collect($request->input('class_names'))
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
        $name = trim((string) $request->input('name'));
        $examDate = (string) $request->input('exam_date');
        $firstStart = (string) $request->input('start_time');
        $gapMinutes = (int) $request->input('gap_minutes');

        $result = DB::transaction(function () use ($roomIds, $subjectRows, $classNames, $name, $examDate, $firstStart, $gapMinutes): array {
            $rooms = Room::query()->whereIn('id', $roomIds)->get()->keyBy('id');
            $subjects = Subject::query()->whereIn('id', collect($subjectRows)->pluck('subject_id'))->get()->keyBy('id');

            $totalCapacity = (int) $rooms->sum('capacity');
            $studentIds = $this->orderedStudentIds($classNames);
            $totalStudents = count($studentIds);

            if ($totalStudents === 0) {
                throw ValidationException::withMessages([
                    'class_names' => 'Tidak ada siswa pada kelas yang dipilih. Pastikan kelas memiliki siswa sebelum membuat sesi.',
                ]);
            }

            if ($rooms->isEmpty()) {
                throw ValidationException::withMessages([
                    'rooms' => 'Pilih minimal satu ruangan.',
                ]);
            }

            if ($totalCapacity <= 0) {
                throw ValidationException::withMessages([
                    'rooms' => 'Total kapasitas ruangan terpilih harus lebih dari 0 kursi.',
                ]);
            }

            if ($subjectRows === []) {
                throw ValidationException::withMessages([
                    'subjects' => 'Tambahkan minimal satu mata pelajaran.',
                ]);
            }

            $sessionDuration = collect($subjectRows)->sum('duration_minutes');
            $numberOfSessions = (int) ceil($totalStudents / $totalCapacity);
            $className = implode(', ', $classNames);
            $sessionStart = Carbon::createFromFormat('H:i', $firstStart);
            $sessionEndMinutes = (int) $sessionStart->format('H') * 60 + (int) $sessionStart->format('i') + $sessionDuration;
            $lastEndMinutes = $sessionEndMinutes + ($numberOfSessions - 1) * ($sessionDuration + $gapMinutes);

            if ($lastEndMinutes >= 1440) {
                throw ValidationException::withMessages([
                    'start_time' => 'Waktu ujian melewati tengah malam. Mulailah lebih awal, kurangi durasi, atau kurangi jeda antar sesi.',
                ]);
            }

            $createdPeriods = [];
            $placed = 0;
            $unfilledSlots = 0;

            for ($n = 1; $n <= $numberOfSessions; $n++) {
                $sessionEnd = $sessionStart->copy()->addMinutes($sessionDuration);

                $period = ExamPeriod::create([
                    'name' => "{$name} - Sesi {$n}",
                    'exam_date' => $examDate,
                    'start_time' => $sessionStart->format('H:i:s'),
                    'end_time' => $sessionEnd->format('H:i:s'),
                ]);

                $conflicts = $this->scheduleConflicts($roomIds, $rooms, $subjects, $subjectRows, $examDate, $sessionStart);

                if ($conflicts !== []) {
                    throw ValidationException::withMessages(['subjects' => $conflicts]);
                }

                foreach ($roomIds as $roomId) {
                    $subjectStart = $sessionStart->copy();

                    foreach ($subjectRows as $row) {
                        ExamSchedule::create([
                            'subject_id' => $row['subject_id'],
                            'room_id' => $roomId,
                            'exam_period_id' => $period->id,
                            'class_name' => $className,
                            'exam_date' => $examDate,
                            'start_time' => $subjectStart->format('H:i:s'),
                            'end_time' => $subjectStart->copy()->addMinutes($row['duration_minutes'])->format('H:i:s'),
                            'duration_minutes' => $row['duration_minutes'],
                            'status' => ExamSchedule::STATUS_SCHEDULED,
                        ]);

                        $subjectStart->addMinutes($row['duration_minutes']);
                    }
                }

                $slice = array_slice($studentIds, $placed, $totalCapacity);
                $this->placeStudentsIntoRooms($period, $roomIds, $slice);
                $placed += count($slice);

                $rotation = $this->assignRoomSupervisors($period, $roomIds);
                $unfilledSlots += $rotation['total_slots'] - $rotation['filled_slots'];

                $createdPeriods[] = $period;
                $sessionStart = $sessionEnd->copy()->addMinutes($gapMinutes);
            }

            return [
                'periods' => $createdPeriods,
                'numberOfSessions' => $numberOfSessions,
                'totalStudents' => $totalStudents,
                'unfilledSlots' => $unfilledSlots,
            ];
        });

        $names = collect($result['periods'])
            ->map(fn (ExamPeriod $period) => $period->name)
            ->implode(', ');

        $message = "Berhasil membuat {$result['numberOfSessions']} sesi ({$names}) untuk {$result['totalStudents']} siswa di ".count($roomIds).' ruangan.';

        if ($result['unfilledSlots'] > 0) {
            $message .= ' Peringatan: '.$result['unfilledSlots'].' slot pengawas belum terisi karena jumlah pengawas aktif kurang dari kebutuhan total.';
        }

        return redirect()->route('admin.exam-periods.index')
            ->with('success', $message);
    }

    public function show(ExamPeriod $examPeriod): View
    {
        $examPeriod->load([
            'schedules.subject',
            'schedules.room',
            'roomAssignments.student.user',
            'roomAssignments.room',
            'supervisorRoomAssignments.supervisor.user',
            'supervisorRoomAssignments.room',
        ]);
        $examPeriod->loadCount('schedules');

        $roomGroups = $examPeriod->schedules
            ->groupBy(fn (ExamSchedule $schedule) => $schedule->room_id)
            ->map(function (Collection $schedules, int $roomId) use ($examPeriod): array {
                return [
                    'room' => $schedules->first()?->room,
                    'schedules' => $schedules,
                    'supervisors' => $examPeriod->supervisorRoomAssignments
                        ->where('room_id', $roomId)
                        ->map->supervisor
                        ->values(),
                    'assignments' => $examPeriod->roomAssignments
                        ->where('room_id', $roomId)
                        ->sortBy('seat_number')
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group) => $group['room']?->room_number ?? PHP_INT_MAX)
            ->values();

        return view('admin.exam-periods.show', [
            'examPeriod' => $examPeriod,
            'roomGroups' => $roomGroups,
            'statuses' => ExamSchedule::STATUSES,
        ]);
    }

    public function roomRoster(ExamPeriod $examPeriod, Room $room): View
    {
        $assignments = ExamRoomAssignment::query()
            ->with(['student.user'])
            ->where('exam_period_id', $examPeriod->id)
            ->where('room_id', $room->id)
            ->orderBy('seat_number')
            ->get();

        return view('admin.exam-periods.room-roster', [
            'examPeriod' => $examPeriod,
            'room' => $room,
            'assignments' => $assignments,
        ]);
    }

    public function groupsCreate(ExamPeriod $examPeriod): View
    {
        return view('admin.exam-periods.groups-create', [
            'examPeriod' => $examPeriod,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('room_number')->get(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    /**
     * Proses bulk assignment: loop setiap ruangan x setiap mapel, lalu buat
     * record ExamSchedule. Seluruh kombinasi divalidasi lebih dulu (terhadap
     * jadwal yang sudah ada dan sesama baris dalam request yang sama) di dalam
     * satu transaksi DB sehingga all-or-nothing.
     */
    public function groupsStore(StoreExamPeriodGroupsRequest $request, ExamPeriod $examPeriod): RedirectResponse
    {
        $roomIds = array_map('intval', $request->input('rooms'));
        $subjectRows = $request->input('subjects');
        $classNames = collect($request->input('class_names'))
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
        $className = implode(', ', $classNames);
        $examDate = $examPeriod->exam_date->toDateString();
        $created = 0;
        $unfilledSlots = 0;

        DB::transaction(function () use ($roomIds, $subjectRows, $className, $classNames, $examDate, $examPeriod, &$created, &$unfilledSlots): void {
            $rooms = Room::query()->whereIn('id', $roomIds)->get()->keyBy('id');
            $subjects = Subject::query()
                ->whereIn('id', collect($subjectRows)->pluck('subject_id'))
                ->get()
                ->keyBy('id');

            $planned = [];
            $conflicts = [];

            foreach ($roomIds as $roomId) {
                $roomName = $rooms->get($roomId)?->display_name ?? "Ruangan #{$roomId}";

                foreach ($subjectRows as $row) {
                    $subject = $subjects->get((int) $row['subject_id']);
                    $subjectName = $subject?->name ?? 'tanpa nama';
                    $start = Carbon::createFromFormat('H:i', $row['start_time']);
                    $startMinutes = (int) $start->format('H') * 60 + (int) $start->format('i');
                    $endMinutes = $startMinutes + (int) $row['duration_minutes'];
                    $label = self::windowLabel($startMinutes, $endMinutes);

                    $existing = ExamSchedule::findConflicting($roomId, $examDate, $startMinutes, $endMinutes);
                    if ($existing !== null) {
                        $conflicts[] = "Ruangan {$roomName} bentrok dengan ujian "
                            .($existing->subject?->name ?? 'tanpa nama')
                            .' yang sudah ada pukul '
                            .$existing->startLabel()
                            .'–'
                            .$existing->endLabel()
                            ." (mapel: {$subjectName}, {$label}).";
                    }

                    foreach ($planned[$roomId] ?? [] as $window) {
                        if ($startMinutes < $window['end'] && $endMinutes > $window['start']) {
                            $conflicts[] = "Ruangan {$roomName} bentrok dengan jadwal "
                                .$window['subject']
                                ." dalam kelompok yang sama (mapel: {$subjectName}, {$label} vs "
                                .$window['label']
                                .').';
                        }
                    }

                    $planned[$roomId][] = [
                        'start' => $startMinutes,
                        'end' => $endMinutes,
                        'subject' => $subjectName,
                        'label' => $label,
                    ];
                }
            }

            if ($conflicts !== []) {
                throw ValidationException::withMessages(['subjects' => $conflicts]);
            }

            foreach ($roomIds as $roomId) {
                foreach ($subjectRows as $row) {
                    $start = Carbon::createFromFormat('H:i', $row['start_time']);

                    ExamSchedule::create([
                        'subject_id' => $row['subject_id'],
                        'room_id' => $roomId,
                        'exam_period_id' => $examPeriod->id,
                        'class_name' => $className,
                        'exam_date' => $examDate,
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $start->copy()->addMinutes((int) $row['duration_minutes'])->format('H:i:s'),
                        'duration_minutes' => $row['duration_minutes'],
                        'status' => ExamSchedule::STATUS_SCHEDULED,
                    ]);

                    $created++;
                }
            }

            $this->placeStudentsIntoRooms($examPeriod, $roomIds, $this->orderedStudentIds($classNames));

            $rotation = $this->assignRoomSupervisors($examPeriod, $roomIds);
            $unfilledSlots = $rotation['total_slots'] - $rotation['filled_slots'];
        });

        $message = "{$created} jadwal ujian berhasil dibuat untuk {$examPeriod->name}.";

        if ($unfilledSlots > 0) {
            $message .= ' Peringatan: '.$unfilledSlots.' slot pengawas belum terisi karena jumlah pengawas aktif kurang dari kebutuhan total.';
        }

        return redirect()->route('admin.exam-periods.show', $examPeriod)
            ->with('success', $message);
    }

    /**
     * Generate rotasi pengawas untuk satu periode (isi ruangan yang masih
     * kosong saja, TIDAK menimpa penugasan yang sudah ada). Ruangan yang
     * dipertimbangkan adalah semua ruangan yang memiliki jadwal pada periode
     * tersebut.
     */
    public function supervisorRotation(ExamPeriod $examPeriod): RedirectResponse
    {
        $roomIds = ExamSchedule::query()
            ->where('exam_period_id', $examPeriod->id)
            ->distinct()
            ->pluck('room_id')
            ->filter()
            ->values()
            ->all();

        if ($roomIds === []) {
            return back()->with('error', 'Sesi ini belum memiliki jadwal ujian sehingga belum ada ruangan yang perlu diisi pengawas.');
        }

        $result = $this->assignRoomSupervisors($examPeriod, $roomIds);
        $created = $result['assignments'];
        $totalSlots = $result['total_slots'];
        $filledSlots = $result['filled_slots'];

        if ($created === []) {
            if ($filledSlots >= $totalSlots) {
                return back()->with('info', 'Semua slot pengawas pada sesi ini sudah terisi.');
            }

            return back()->with('info', "Pengawas aktif tidak mencukupi: baru {$filledSlots} dari {$totalSlots} slot pengawas yang terisi.");
        }

        $message = 'Rotasi pengawas berhasil: '.count($created).' slot pengawas baru terisi.';

        if ($filledSlots < $totalSlots) {
            $message .= " Pengawas aktif tidak mencukupi kebutuhan {$totalSlots} slot (baru terisi {$filledSlots}); slot yang belum terisi tampil sebagai \"Belum ditugaskan\".";
        }

        return back()->with('success', $message);
    }

    public function destroy(ExamPeriod $examPeriod): RedirectResponse
    {
        $examPeriod->delete();

        return redirect()->route('admin.exam-periods.index')->with('success', 'Sesi ujian berhasil dihapus.');
    }

    /**
     * Label rentang waktu (HH:MM–HH:MM) dari menit sejak tengah malam.
     */
    private static function windowLabel(int $startMinutes, int $endMinutes): string
    {
        $end = $endMinutes % 1440;

        return sprintf('%02d:%02d–%02d:%02d', intdiv($startMinutes, 60), $startMinutes % 60, intdiv($end, 60), $end % 60);
    }

    /**
     * ID siswa terurut: mengikuti urutan kelas yang dipilih, lalu alfabetis
     * nama siswa (nisn sebagai tie-breaker) di dalam tiap kelas.
     *
     * @param  array<int, string>  $classNames
     * @return array<int, int>
     */
    private function orderedStudentIds(array $classNames): array
    {
        $ids = [];

        foreach ($classNames as $className) {
            $ids = array_merge($ids, Student::query()
                ->join('users', 'users.id', '=', 'students.user_id')
                ->where('students.class_name', $className)
                ->orderBy('users.name')
                ->orderBy('students.nisn')
                ->select('students.id')
                ->pluck('id')
                ->all());
        }

        return $ids;
    }

    /**
     * Cek bentrok seluruh kombinasi ruangan × mapel pada satu sesi terhadap
     * jadwal existing. Mapel dalam satu sesi berurutan back-to-back sehingga
     * tidak ada bentrok internal antar baris pada sesi yang sama.
     *
     * @param  array<int, int>  $roomIds
     * @param  Collection<int, Room>  $rooms
     * @param  Collection<int, Subject>  $subjects
     * @param  array<int, array{subject_id: int, duration_minutes: int}>  $subjectRows
     * @return array<int, string>
     */
    private function scheduleConflicts(
        array $roomIds,
        Collection $rooms,
        Collection $subjects,
        array $subjectRows,
        string $examDate,
        Carbon $sessionStart,
    ): array {
        $conflicts = [];

        foreach ($roomIds as $roomId) {
            $roomName = $rooms->get($roomId)?->display_name ?? "Ruangan #{$roomId}";
            $subjectStart = $sessionStart->copy();

            foreach ($subjectRows as $row) {
                $subject = $subjects->get($row['subject_id']);
                $subjectName = $subject?->name ?? 'tanpa nama';
                $startMinutes = (int) $subjectStart->format('H') * 60 + (int) $subjectStart->format('i');
                $endMinutes = $startMinutes + $row['duration_minutes'];
                $label = self::windowLabel($startMinutes, $endMinutes);

                $existing = ExamSchedule::findConflicting($roomId, $examDate, $startMinutes, $endMinutes);

                if ($existing !== null) {
                    $conflicts[] = "Ruangan {$roomName} bentrok dengan ujian "
                        .($existing->subject?->name ?? 'tanpa nama')
                        .' yang sudah ada pukul '
                        .$existing->startLabel()
                        .'–'
                        .$existing->endLabel()
                        ." (mapel: {$subjectName}, {$label}).";
                }

                $subjectStart->addMinutes($row['duration_minutes']);
            }
        }

        return $conflicts;
    }

    /**
     * Penempatan otomatis siswa ke ruangan untuk satu sesi ujian.
     *
     * Siswa diterima sebagai daftar ID yang sudah terurut (urutan kelas dipilih
     * lalu alfabetis nama). Ruangan diurutkan ascending berdasarkan nomor ruangan dan
     * diisi sequential: ruangan pertama penuh dulu, lalu lanjut ke ruangan
     * berikutnya (boleh ada campuran ekor kelas A dan awal kelas B dalam satu
     * ruangan). Nomor kursi dihitung ulang dari 1 di tiap ruangan. Dipanggil di
     * dalam transaksi yang sama dengan pembuatan ExamSchedule sehingga kegagalan
     * membatalkan seluruhnya (all-or-nothing).
     *
     * @param  array<int, int>  $roomIds
     * @param  array<int, int>  $studentIds
     */
    private function placeStudentsIntoRooms(ExamPeriod $examPeriod, array $roomIds, array $studentIds): void
    {
        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->orderBy('room_number')
            ->get();

        $alreadyPlaced = ExamRoomAssignment::query()
            ->where('exam_period_id', $examPeriod->id)
            ->whereIn('student_id', $studentIds)
            ->count();

        if ($alreadyPlaced > 0) {
            throw ValidationException::withMessages([
                'class_names' => "{$alreadyPlaced} siswa dari kelas terpilih sudah memiliki penempatan ruangan pada sesi ini.",
            ]);
        }

        $totalCapacity = $rooms->sum('capacity');
        $totalStudents = count($studentIds);

        if ($totalStudents > $totalCapacity) {
            throw ValidationException::withMessages([
                'rooms' => "Kapasitas ruangan tidak mencukupi: {$totalStudents} siswa akan ditempatkan, tetapi total kapasitas ruangan terpilih hanya {$totalCapacity} kursi.",
            ]);
        }

        $assignments = [];
        $roomIndex = 0;
        $seat = 0;

        foreach ($studentIds as $studentId) {
            while ($roomIndex < $rooms->count() && $seat >= $rooms[$roomIndex]->capacity) {
                $roomIndex++;
                $seat = 0;
            }

            if ($roomIndex >= $rooms->count()) {
                break;
            }

            $seat++;

            $assignments[] = [
                'exam_period_id' => $examPeriod->id,
                'student_id' => $studentId,
                'room_id' => $rooms[$roomIndex]->id,
                'seat_number' => $seat,
            ];
        }

        if ($assignments !== []) {
            ExamRoomAssignment::insert($assignments);
        }
    }

    /**
     * Rotasi penugasan ruangan pengawas untuk satu periode ujian.
     *
     * Algoritma:
     *  1. Ambil ruangan yang dipakai periode ini (urutan nomor ruangan, deterministik)
     *     beserta kebutuhan pengawasnya (rooms.supervisor_count, dibatasi
     *     maksimum config max_supervisors_per_room). Setiap ruangan wajib
     *     mendapat SEBANYAK supervisor_count slot pengawas.
     *  2. Ambil pengawas aktif (users.is_active = true).
     *  3. 1 pengawas maksimal 1 slot pada tanggal periode (unique constraint
     *     (periode, tanggal, pengawas) dijamin di database).
     *  4. Konstraint utama: hindari pengawas mendapat ruangan yang sama di
     *     tanggal yang sama (termasuk lintas periode/gelombang), dan utamakan
     *     pengawas yang paling lama tidak bertugas di ruangan itu.
     *  5. Fallback: bila pengawas aktif tidak mencukupi kebutuhan slot
     *     (SUM supervisor_count), slot yang bisa diisi tetap diisi dan sisanya
     *     dibiarkan kosong (tampil "Belum ditugaskan").
     *  6. Distribusi merata: antrean diurutkan berdasarkan beban (jumlah
     *     penugasan di periode ini) naik, lalu nama naik.
     *
     * Method ini hanya mengisi slot (periode, tanggal, ruangan) yang BELUM punya
     * pengawas, sehingga aman dipanggil saat auto-generate/groupsStore maupun
     * tombol "Generate Rotasi Pengawas" tanpa menimpa penugasan lama.
     *
     * @param  array<int, int>  $roomIds
     * @return array{assignments: array<int, array<string, mixed>>, total_slots: int, filled_slots: int}
     */
    private function assignRoomSupervisors(ExamPeriod $examPeriod, array $roomIds): array
    {
        $date = $examPeriod->exam_date->toDateString();

        $rooms = Room::query()->whereIn('id', $roomIds)->orderBy('room_number')->get();

        $active = Supervisor::query()
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with('user')
            ->orderBy('user_id')
            ->get();

        $maxPerRoom = (int) config('exam.max_supervisors_per_room', 3);

        $needs = $rooms->mapWithKeys(fn (Room $room) => [
            $room->id => max(1, min($maxPerRoom, (int) $room->supervisor_count)),
        ]);

        $totalSlots = $needs->sum();

        if ($rooms->isEmpty() || $active->isEmpty()) {
            return ['assignments' => [], 'total_slots' => $totalSlots, 'filled_slots' => 0];
        }

        $existing = SupervisorRoomAssignment::query()
            ->where('exam_period_id', $examPeriod->id)
            ->get();

        $filledByRoom = $existing
            ->filter(fn ($assignment) => $assignment->exam_date?->toDateString() === $date)
            ->groupBy('room_id')
            ->map->count();
        $burden = $existing->groupBy('supervisor_id')->map->count();
        $assignedSupervisorIds = $existing->pluck('supervisor_id')->all();

        $activeIds = $active->pluck('id');

        // Supervisor pernah bertugas di ruangan apa saja pada TANGGAL ini
        // (lintas periode/gelombang), untuk mencegah ruangan sama di hari sama.
        $roomHistoryOnDate = SupervisorRoomAssignment::query()
            ->where('exam_date', $date)
            ->whereIn('supervisor_id', $activeIds)
            ->get()
            ->groupBy('supervisor_id')
            ->map(fn ($group) => $group->pluck('room_id')->all());

        // Tanggal terakhir tiap pasangan (pengawas, ruangan) pernah bertugas,
        // untuk memprioritaskan pengawas yang paling lama tidak dapat ruangan itu.
        $lastDateByPair = SupervisorRoomAssignment::query()
            ->where('exam_date', '<=', $date)
            ->whereIn('supervisor_id', $activeIds)
            ->get()
            ->groupBy(fn ($assignment) => $assignment->supervisor_id.'-'.$assignment->room_id)
            ->map(fn ($group) => $group->max(fn ($assignment) => $assignment->exam_date?->toDateString()));

        $queue = $active
            ->sortBy(fn (Supervisor $supervisor) => [
                $burden->get($supervisor->id, 0),
                $supervisor->user?->name ?? '',
            ])
            ->values();

        $used = [];
        $assignments = [];
        $filledSlots = 0;

        foreach ($rooms as $room) {
            $need = $needs->get($room->id, 1);
            $filled = $filledByRoom->get($room->id, 0);

            while ($filled < $need) {
                $candidates = $queue
                    ->filter(fn (Supervisor $supervisor) => ! in_array($supervisor->id, $assignedSupervisorIds, true))
                    ->filter(fn (Supervisor $supervisor) => ! isset($used[$supervisor->id]))
                    ->sortBy(fn (Supervisor $supervisor) => [
                        in_array($room->id, $roomHistoryOnDate[$supervisor->id] ?? [], true),
                        in_array($room->id, $used[$supervisor->id] ?? [], true),
                        count($used[$supervisor->id] ?? []),
                        $burden->get($supervisor->id, 0),
                        $lastDateByPair->get($supervisor->id.'-'.$room->id) ?? '',
                        $supervisor->user?->name ?? '',
                    ])
                    ->values();

                if ($candidates->isEmpty()) {
                    break;
                }

                $supervisor = $candidates->first();

                $assignments[] = [
                    'exam_period_id' => $examPeriod->id,
                    'exam_date' => $date,
                    'supervisor_id' => $supervisor->id,
                    'room_id' => $room->id,
                    'rotation_index' => $existing->where('supervisor_id', $supervisor->id)->count() + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $used[$supervisor->id] = array_merge($used[$supervisor->id] ?? [], [$room->id]);
                $queue = $queue->reject(fn (Supervisor $item) => $item->id === $supervisor->id);
                $filled++;
                $filledSlots++;
            }
        }

        if ($assignments !== []) {
            SupervisorRoomAssignment::insert($assignments);
        }

        return [
            'assignments' => $assignments,
            'total_slots' => $totalSlots,
            'filled_slots' => $filledSlots,
        ];
    }
}
