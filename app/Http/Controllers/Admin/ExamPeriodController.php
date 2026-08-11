<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamPeriodGroupsRequest;
use App\Http\Requests\Admin\StoreExamPeriodRequest;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
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
        $className = $request->input('class_name');
        $examDate = $examPeriod->exam_date->toDateString();
        $created = 0;

        DB::transaction(function () use ($roomIds, $subjectRows, $className, $examDate, $examPeriod, &$created): void {
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
}
