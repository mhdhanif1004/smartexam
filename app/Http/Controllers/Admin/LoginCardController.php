<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardSetting;
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

        return view('admin.student-cards.index', compact(
            'type',
            'classes',
            'students',
            'selectedClass',
            'rooms',
            'supervisors',
            'selectedRoom',
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

        return view('admin.student-cards.preview', compact('students', 'setting', 'tanggalCetak'));
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

        $pdf = Pdf::loadView('admin.student-cards.print', compact('students', 'setting', 'tanggalCetak'))
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
}
