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
            'rooms' => Room::query()->orderBy('name')->get(),
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

                $createdPeriods[] = $period;
                $sessionStart = $sessionEnd->copy()->addMinutes($gapMinutes);
            }

            return [
                'periods' => $createdPeriods,
                'numberOfSessions' => $numberOfSessions,
                'totalStudents' => $totalStudents,
            ];
        });

        $names = collect($result['periods'])
            ->map(fn (ExamPeriod $period) => $period->name)
            ->implode(', ');

        return redirect()->route('admin.exam-periods.index')
            ->with('success', "Berhasil membuat {$result['numberOfSessions']} sesi ({$names}) untuk {$result['totalStudents']} siswa di ".count($roomIds).' ruangan.');
    }

    public function show(ExamPeriod $examPeriod): View
    {
        $examPeriod->load(['schedules.subject', 'schedules.room']);

        return view('admin.exam-periods.show', [
            'examPeriod' => $examPeriod,
            'statuses' => ExamSchedule::STATUSES,
        ]);
    }

    public function groupsCreate(ExamPeriod $examPeriod): View
    {
        return view('admin.exam-periods.groups-create', [
            'examPeriod' => $examPeriod,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
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

        DB::transaction(function () use ($roomIds, $subjectRows, $className, $classNames, $examDate, $examPeriod, &$created): void {
            $rooms = Room::query()->whereIn('id', $roomIds)->get()->keyBy('id');
            $subjects = Subject::query()
                ->whereIn('id', collect($subjectRows)->pluck('subject_id'))
                ->get()
                ->keyBy('id');

            $planned = [];
            $conflicts = [];

            foreach ($roomIds as $roomId) {
                $roomName = $rooms->get($roomId)?->name ?? "Ruangan #{$roomId}";

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
        });

        return redirect()->route('admin.exam-periods.show', $examPeriod)
            ->with('success', "{$created} jadwal ujian berhasil dibuat untuk {$examPeriod->name}.");
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
            $roomName = $rooms->get($roomId)?->name ?? "Ruangan #{$roomId}";
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
     * lalu alfabetis nama). Ruangan diurutkan ascending berdasarkan nama dan
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
            ->orderBy('name')
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
}
