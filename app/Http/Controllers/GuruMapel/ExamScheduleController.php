<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Traits\ScopesGuruMapel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Jadwal Ujian Mandiri Guru Mapel.
 *
 * Guru membuat ExamPeriod + ExamSchedule untuk mapel-kelas yang dia ampu,
 * berbasis KELAS (classroom_id), bukan ruang fisik (room_id null).
 * Jenis ujian dibatasi exam_types.boleh_dijadwalkan_guru = true.
 */
class ExamScheduleController extends Controller
{
    use ScopesGuruMapel;

    public function index(): View
    {
        $guru = $this->currentGuru();

        $periods = ExamPeriod::query()
            ->with(['creator', 'examType', 'schedules'])
            ->where('created_by_user_id', auth()->id())
            ->withCount('schedules')
            ->orderByDesc('exam_date')
            ->orderByDesc('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('guru_mapel.exam-schedules.index', compact('guru', 'periods'));
    }

    public function create(): View
    {
        $guru = $this->currentGuru();

        $examTypes = ExamType::query()
            ->where('boleh_dijadwalkan_guru', true)
            ->orderBy('sort_order')
            ->get();

        $subjects = $this->ampuSubjects($guru);

        // Map subject_id => daftar kelas untuk dropdown dependen (client-side).
        $classroomsBySubject = $guru->classScopeBySubject();

        return view('guru_mapel.exam-schedules.create', compact(
            'guru', 'examTypes', 'subjects', 'classroomsBySubject'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'exam_type_id' => ['required', 'integer', Rule::exists('exam_types', 'id')->where('boleh_dijadwalkan_guru', true)],
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
        ]);

        // Server-side gate (bukan cuma UI hide): guru hanya boleh kombinasi
        // mapel+kelas yang benar-benar dia ampu.
        abort_unless(
            $guru->isAmpu(subjectId: (int) $validated['subject_id'], classroomId: (int) $validated['classroom_id']),
            403,
            'Anda tidak mengampu kombinasi mapel-kelas ini.'
        );

        $subject = $guru->subjects()->findOrFail((int) $validated['subject_id']);
        $classroom = Classroom::query()->findOrFail((int) $validated['classroom_id']);

        $start = Carbon::createFromFormat('H:i', (string) $validated['start_time']);
        $end = $start->copy()->addMinutes((int) $validated['duration_minutes']);

        if ($end->format('H:i') <= $start->format('H:i')) {
            return back()->withInput()->withErrors(['duration_minutes' => 'Waktu selesai ujian melebihi pukul 24:00. Periksa kembali durasi.']);
        }

        $startMinutes = (int) $start->format('H') * 60 + (int) $start->format('i');

        // Cegah bentrok: 2 jadwal waktu overlap utk KELAS yang sama (classroom variant).
        $conflict = ExamSchedule::findConflicting(
            roomId: null,
            examDate: (string) $validated['exam_date'],
            startMinutes: $startMinutes,
            endMinutes: $startMinutes + (int) $validated['duration_minutes'],
            classroomId: (int) $validated['classroom_id'],
        );

        if ($conflict !== null) {
            return back()->withInput()->withErrors([
                'classroom_id' => 'Kelas '.$classroom->name.' sudah punya ujian lain pukul '
                    .$this->timeRangeLabel($conflict)
                    .'. Pilih waktu lain.'
                    .' (bentrok dengan '.($conflict->subject?->name ?? 'tanpa nama').').',
            ]);
        }

        $gradeLevel = ExamPeriod::extractGradeLevel($classroom->name);

        DB::transaction(function () use ($validated, $classroom, $subject, $end, $start, $gradeLevel) {
            $period = ExamPeriod::create([
                'name' => $subject->name.' — '.$classroom->name,
                'name_prefix' => $subject->name.' — '.$classroom->name,
                'exam_type_id' => (int) $validated['exam_type_id'],
                'grade_level' => $gradeLevel ?? 'X',
                'exam_date' => $validated['exam_date'],
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'created_by_user_id' => auth()->id(),
            ]);

            ExamSchedule::create([
                'subject_id' => (int) $validated['subject_id'],
                'room_id' => null,
                'classroom_id' => (int) $validated['classroom_id'],
                'exam_period_id' => $period->id,
                'class_name' => $classroom->name,
                'exam_date' => $validated['exam_date'],
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'duration_minutes' => (int) $validated['duration_minutes'],
                'status' => ExamSchedule::STATUS_SCHEDULED,
            ]);
        });

        return redirect()->route('guru_mapel.exam-schedules.index')->with('success', 'Jadwal ujian berhasil dibuat.');
    }

    private function timeRangeLabel(ExamSchedule $schedule): string
    {
        $start = substr((string) $schedule->start_time, 0, 5);
        $end = substr((string) $schedule->end_time, 0, 5) !== substr((string) $schedule->start_time, 0, 5) && $schedule->end_time !== null
            ? substr((string) $schedule->end_time, 0, 5)
            : date('H:i', strtotime((string) $schedule->start_time) + $schedule->duration_minutes * 60);

        return $start.'–'.$end;
    }
}
