<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardSetting;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
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

        $roomAssignments = $type === 'pengawas' ? collect() : $this->roomAssignmentsByStudent($students);

        return view('admin.student-cards.index', compact(
            'type',
            'classes',
            'students',
            'selectedClass',
            'rooms',
            'supervisors',
            'selectedRoom',
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
        $roomAssignments = $this->roomAssignmentsByStudent($students);

        return view('admin.student-cards.preview', compact('students', 'setting', 'tanggalCetak', 'roomAssignments'));
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
        $roomAssignments = $this->roomAssignmentsByStudent($students);

        $pdf = Pdf::loadView('admin.student-cards.print', compact('students', 'setting', 'tanggalCetak', 'roomAssignments'))
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
     * Resolusi siswa untuk preview/print. Mendukung tiga sumber, tanpa
     * mengirim ratusan/ribuan ID sebagai parameter terpisah (menghindari
     * batas PHP max_input_vars):
     *   1. student_ids — satu string ID yang dipisah koma (atau array, untuk
     *      kompatibilitas lama).
     *   2. class — nama kelas; semua siswa di kelas itu diambil.
     *   3. tanpa keduanya — semua siswa diambil.
     *
     * @return Collection<int, Student>
     */
    private function resolveStudents(Request $request): Collection
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:peserta,pengawas'],
            'class' => ['nullable', 'string', 'max:100'],
        ]);

        $studentIds = $this->parseIds($request->input('student_ids'));

        $query = Student::query()->with(['user', 'room']);

        if ($studentIds !== []) {
            $query->whereIn('id', $studentIds);
        } elseif ($request->filled('class')) {
            $query->where('class_name', $request->string('class'));
        }

        return $query->orderBy('class_name')->orderBy('nisn')->get();
    }

    /**
     * Resolusi pengawas untuk preview/print (lihat resolveStudents).
     * Sumber: supervisor_ids (string koma/array), room (filter ruangan),
     * atau semua pengawas.
     *
     * @return Collection<int, Supervisor>
     */
    private function resolveSupervisors(Request $request): Collection
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:peserta,pengawas'],
            'room' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $supervisorIds = $this->parseIds($request->input('supervisor_ids'));

        $query = Supervisor::query()->with(['user', 'room']);

        if ($supervisorIds !== []) {
            $query->whereIn('id', $supervisorIds);
        } elseif ($request->filled('room')) {
            $query->where('room_id', $request->integer('room'));
        }

        return $query->orderBy('user_id')->get();
    }

    /**
     * Normalisasi input ID: terima array (lama) atau satu string yang
     * dipisah koma. Nilai non-angka diabaikan.
     *
     * @return list<int>
     */
    private function parseIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }

        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
        }

        return [];
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
