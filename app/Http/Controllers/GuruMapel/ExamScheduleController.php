<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
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

        // Tier hapus per period untuk modal konfirmasi (messaging saja;
        // server-side destroy tetap melakukan pengecekan keras).
        $deleteTiers = [];
        foreach ($periods as $period) {
            [$hasStarted, $hasConfirmedAttendance] = $this->periodState($period);
            $deleteTiers[$period->id] = [
                'mode' => $hasStarted ? 'blocked' : ($hasConfirmedAttendance ? 'warning' : 'simple'),
                'has_started' => $hasStarted,
                'has_confirmed_attendance' => $hasConfirmedAttendance,
            ];
        }

        return view('guru_mapel.exam-schedules.index', compact('guru', 'periods', 'deleteTiers'));
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

    public function edit(ExamPeriod $examPeriod): View
    {
        $guru = $this->currentGuru();

        [$hasStarted, $hasConfirmedAttendance] = $this->periodState($examPeriod);

        $examPeriod->load('examType', 'schedules', 'creator');

        $examTypes = ExamType::query()
            ->where('boleh_dijadwalkan_guru', true)
            ->orderBy('sort_order')
            ->get();

        $subjects = $this->ampuSubjects($guru);
        $classroomsBySubject = $guru->classScopeBySubject();

        return view('guru_mapel.exam-schedules.edit', compact(
            'guru', 'examTypes', 'subjects', 'classroomsBySubject', 'examPeriod',
            'hasStarted', 'hasConfirmedAttendance'
        ));
    }

    public function update(Request $request, ExamPeriod $examPeriod): RedirectResponse
    {
        $guru = $this->currentGuru();

        [$hasStarted, $hasConfirmedAttendance] = $this->periodState($examPeriod);

        $validated = $request->validate([
            'exam_type_id' => ['required', 'integer', Rule::exists('exam_types', 'id')->where('boleh_dijadwalkan_guru', true)],
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'confirm_attendance_reset' => ['nullable', 'boolean'],
        ]);

        // SERVER-SIDE GATES — tidak bergantung form (paksa via request pun tetap ke-reject).

        // 1) Field struktural terkunci kalau sesi sudah pernah dimulai siswa.
        $structuralChanged = (int) $validated['exam_type_id'] !== (int) $examPeriod->exam_type_id
            || (int) $validated['subject_id'] !== (int) $examPeriod->schedules()->first()?->subject_id
            || (int) $validated['classroom_id'] !== (int) $examPeriod->schedules()->first()?->classroom_id;

        if ($hasStarted && $structuralChanged) {
            return back()->withInput()->withErrors([
                'exam_type_id' => 'Sesi ujian sudah dimulai siswa. Jenis ujian, mapel, dan kelas tidak bisa diubah lagi.',
            ]);
        }

        // 2) Ubah kelas/jenis/mapel saat absensi sudah dikonfirmasi → wajib flag eksplisit.
        if (! $hasStarted && $hasConfirmedAttendance && $structuralChanged) {
            if (! $request->boolean('confirm_attendance_reset')) {
                return back()->withInput()->withErrors([
                    'confirm_attendance_reset' => 'Mengubah jenis ujian, mapel, atau kelas akan menghapus data absensi yang sudah dikonfirmasi. Centang konfirmasi untuk melanjutkan.',
                ]);
            }
        }

        // 3) Hanya boleh untuk mapel+kelas yang dia ampu (isAmpu gate — sama seperti store).
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

        // 4) Conflict check — mengecualikan jadwal ini sendiri.
        $schedule = $examPeriod->schedules()->first();
        $startMinutes = (int) $start->format('H') * 60 + (int) $start->format('i');

        $conflict = ExamSchedule::findConflicting(
            roomId: null,
            examDate: (string) $validated['exam_date'],
            startMinutes: $startMinutes,
            endMinutes: $startMinutes + (int) $validated['duration_minutes'],
            classroomId: (int) $validated['classroom_id'],
            excludeId: $schedule?->id,
        );

        if ($conflict !== null) {
            return back()->withInput()->withErrors([
                'classroom_id' => 'Kelas '.$classroom->name.' sudah punya ujian lain pukul '
                    .$this->timeRangeLabel($conflict)
                    .'. Pilih waktu lain.'
                    .' (bentrok dengan '.($conflict->subject?->name ?? 'tanpa nama').').',
            ]);
        }

        DB::transaction(function () use ($examPeriod, $schedule, $validated, $classroom, $subject, $end, $start, $hasConfirmedAttendance, $structuralChanged) {
            $examPeriod->update([
                'name' => $subject->name.' — '.$classroom->name,
                'name_prefix' => $subject->name.' — '.$classroom->name,
                'exam_type_id' => (int) $validated['exam_type_id'],
                'grade_level' => ExamPeriod::extractGradeLevel($classroom->name) ?? 'X',
                'exam_date' => $validated['exam_date'],
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
            ]);

            $schedule->update([
                'subject_id' => (int) $validated['subject_id'],
                'classroom_id' => (int) $validated['classroom_id'],
                'class_name' => $classroom->name,
                'exam_date' => $validated['exam_date'],
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'duration_minutes' => (int) $validated['duration_minutes'],
            ]);

            // Reset absensi terkait bila struktur berubah setelah ada konfirmasi
            // (jadwal/kelas/jenis baru → peserta lama tidak relevan lagi).
            if ($hasConfirmedAttendance && $structuralChanged) {
                ExamSession::query()
                    ->where('exam_schedule_id', $schedule->id)
                    ->where('attendance_confirmed', true)
                    ->update([
                        'attendance_status' => null,
                        'attendance_confirmed' => false,
                        'attendance_confirmed_at' => null,
                        'attendance_confirmed_by' => null,
                    ]);
            }
        });

        return redirect()->route('guru_mapel.exam-schedules.index')->with('success', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(ExamPeriod $examPeriod): RedirectResponse
    {
        $guru = $this->currentGuru();

        [$hasStarted, $hasConfirmedAttendance] = $this->periodState($examPeriod);

        // Blocked: sesi sudah pernah dikerjakan siswa → TIDAK bisa dihapus
        // (histori/nilai tidak boleh dimusnahkan).
        if ($hasStarted) {
            return back()->with('error', 'Sesi ujian sudah dimulai siswa. Tidak dapat dihapus.');
        }

        DB::transaction(function () use ($examPeriod) {
            $examPeriod->tokens()->delete();
            $examPeriod->schedules()->delete();
            $examPeriod->delete();
        });

        $message = $hasConfirmedAttendance
            ? 'Jadwal ujian dihapus. Data absensi yang sudah dikonfirmasi ikut terhapus.'
            : 'Jadwal ujian berhasil dihapus.';

        return redirect()->route('guru_mapel.exam-schedules.index')->with('success', $message);
    }

    /**
     * State period untuk gating edit/delete.
     *
     * @return array{0: bool, 1: bool} [hasStarted, hasConfirmedAttendance]
     */
    private function periodState(ExamPeriod $examPeriod): array
    {
        $scheduleIds = $examPeriod->schedules()->pluck('id');

        $hasStarted = false;
        $hasConfirmedAttendance = false;

        if ($scheduleIds->isNotEmpty()) {
            $sessions = ExamSession::query()
                ->whereIn('exam_schedule_id', $scheduleIds)
                ->get(['started_at', 'attendance_confirmed']);

            $hasStarted = $sessions->contains(fn ($session) => $session->started_at !== null);
            $hasConfirmedAttendance = $sessions->contains('attendance_confirmed', true);
        }

        return [$hasStarted, $hasConfirmedAttendance];
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
