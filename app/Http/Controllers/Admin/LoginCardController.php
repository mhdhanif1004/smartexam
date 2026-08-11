<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardSetting;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LoginCardController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString() === 'pengawas' ? 'pengawas' : 'peserta';

        $classes = collect();
        $students = collect();
        $selectedClass = '';
        $rooms = collect();
        $supervisors = collect();
        $selectedRoom = null;

        if ($type === 'pengawas') {
            $rooms = Room::query()->orderBy('name')->get();

            $supervisors = Supervisor::query()
                ->with(['user', 'room'])
                ->when($request->filled('room'), fn ($query) => $query->where('room_id', $request->integer('room')))
                ->orderBy('user_id')
                ->get();

            if ($request->filled('room')) {
                $selectedRoom = $request->integer('room');
            }
        } else {
            $classes = Student::query()->distinct()->orderBy('class_name')->pluck('class_name');

            if ($request->filled('class')) {
                $students = Student::query()
                    ->with(['user', 'room'])
                    ->where('class_name', $request->string('class'))
                    ->orderBy('class_name')
                    ->orderBy('nisn')
                    ->get();

                $selectedClass = $request->string('class')->toString();
            } else {
                // Default: show all students from all classes
                $students = Student::query()
                    ->with(['user', 'room'])
                    ->orderBy('class_name')
                    ->orderBy('nisn')
                    ->get();

                $selectedClass = 'all';
            }
        }

        $sessionNamesByRoom = $type === 'pengawas' ? [] : $this->sessionNamesByRoom($students);
        $roomAssignments = $type === 'pengawas' ? collect() : $this->roomAssignmentsByStudent($students);

        return view('admin.student-cards.index', compact(
            'type',
            'classes',
            'students',
            'selectedClass',
            'rooms',
            'supervisors',
            'selectedRoom',
            'sessionNamesByRoom',
            'roomAssignments',
        ));
    }

    public function preview(Request $request): View
    {
        $setting = CardSetting::current() ?? new CardSetting;
        $tanggalCetak = $this->formatTanggalIndonesia(now());

        if ($this->isPengawas($request)) {
            $supervisors = $this->resolveSupervisors($request);

            return view('admin.student-cards.preview-pengawas', compact('supervisors', 'setting', 'tanggalCetak'));
        }

        $students = $this->resolveStudents($request);
        $sessionNamesByRoom = $this->sessionNamesByRoom($students);
        $roomAssignments = $this->roomAssignmentsByStudent($students);

        return view('admin.student-cards.preview', compact('students', 'setting', 'tanggalCetak', 'sessionNamesByRoom', 'roomAssignments'));
    }

    public function print(Request $request): Response
    {
        $setting = CardSetting::current() ?? new CardSetting;
        $tanggalCetak = $this->formatTanggalIndonesia(now());

        if ($this->isPengawas($request)) {
            $supervisors = $this->resolveSupervisors($request);

            $pdf = Pdf::loadView('admin.student-cards.print-pengawas', compact('supervisors', 'setting', 'tanggalCetak'))
                ->setPaper('a4', 'portrait');

            return $pdf->download('kartu-login-pengawas.pdf');
        }

        $students = $this->resolveStudents($request);
        $sessionNamesByRoom = $this->sessionNamesByRoom($students);
        $roomAssignments = $this->roomAssignmentsByStudent($students);

        $pdf = Pdf::loadView('admin.student-cards.print', compact('students', 'setting', 'tanggalCetak', 'sessionNamesByRoom', 'roomAssignments'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('kartu-login-peserta.pdf');
    }

    private function isPengawas(Request $request): bool
    {
        return $request->string('type')->toString() === 'pengawas';
    }

    private function formatTanggalIndonesia(Carbon $date): string
    {
        $bulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $date->format('j').' '.$bulan[$date->month - 1].' '.$date->year;
    }

    /**
     * Validate the selected students and load them with their accounts.
     *
     * @return Collection<int, Student>
     */
    private function resolveStudents(Request $request): Collection
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        return Student::query()
            ->with(['user', 'room'])
            ->whereIn('id', $validated['student_ids'])
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->get();
    }

    /**
     * Validate the selected supervisors and load them with their accounts.
     *
     * @return Collection<int, Supervisor>
     */
    private function resolveSupervisors(Request $request): Collection
    {
        $validated = $request->validate([
            'supervisor_ids' => ['required', 'array', 'min:1'],
            'supervisor_ids.*' => ['integer', 'exists:supervisors,id'],
        ]);

        return Supervisor::query()
            ->with(['user', 'room'])
            ->whereIn('id', $validated['supervisor_ids'])
            ->orderBy('user_id')
            ->get();
    }

    /**
     * Peta room_id => nama sesi ujian (ExamPeriod) yang terhubung melalui
     * jadwal ujian ruangan tersebut. Beberapa jadwal pada sesi yang sama
     * dijadikan satu nilai distinct; lebih dari satu sesi dipisahkan koma.
     * Ruangan tanpa jadwal ber-sesi tidak ikut dalam peta (fallback "-").
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, string>
     */
    private function sessionNamesByRoom(Collection $students): array
    {
        $roomIds = $students->pluck('room_id')->filter()->unique()->values();

        if ($roomIds->isEmpty()) {
            return [];
        }

        return ExamSchedule::query()
            ->with('examPeriod')
            ->whereIn('room_id', $roomIds)
            ->whereNotNull('exam_period_id')
            ->get()
            ->groupBy('room_id')
            ->mapWithKeys(function ($schedules, $roomId) {
                $names = $schedules
                    ->map(fn (ExamSchedule $schedule) => $schedule->examPeriod?->name)
                    ->filter()
                    ->unique()
                    ->values();

                return [(int) $roomId => $names->implode(', ')];
            })
            ->all();
    }

    /**
     * Peta student_id => koleksi penempatan ruangan (ExamRoomAssignment) siswa,
     * lengkap dengan ruangan dan sesi (ExamPeriod), diurutkan berdasarkan sesi
     * (tanggal & jam mulai) lalu nama ruangan. Sumber kebenaran penempatan
     * siswa untuk Kartu Login adalah tabel exam_room_assignments, bukan
     * kolom students.room_id (asumsi lama).
     *
     * @param  Collection<int, Student>  $students
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, ExamRoomAssignment>>
     */
    private function roomAssignmentsByStudent(Collection $students): \Illuminate\Support\Collection
    {
        $studentIds = $students->pluck('id')->all();

        if ($studentIds === []) {
            return collect();
        }

        return ExamRoomAssignment::query()
            ->with(['room', 'examPeriod'])
            ->join('exam_periods', 'exam_periods.id', '=', 'exam_room_assignments.exam_period_id')
            ->whereIn('exam_room_assignments.student_id', $studentIds)
            ->orderBy('exam_periods.exam_date')
            ->orderBy('exam_periods.start_time')
            ->orderBy('exam_room_assignments.room_id')
            ->get(['exam_room_assignments.*'])
            ->groupBy('student_id');
    }
}
