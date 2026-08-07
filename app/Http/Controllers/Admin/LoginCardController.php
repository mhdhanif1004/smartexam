<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                    ->with('user')
                    ->where('class_name', $request->string('class'))
                    ->orderBy('class_name')
                    ->orderBy('nisn')
                    ->get();

                $selectedClass = $request->string('class')->toString();
            } else {
                // Default: show all students from all classes
                $students = Student::query()
                    ->with('user')
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
        if ($this->isPengawas($request)) {
            $supervisors = $this->resolveSupervisors($request);

            return view('admin.student-cards.print-pengawas', ['supervisors' => $supervisors]);
        }

        $students = $this->resolveStudents($request);

        return view('admin.student-cards.print', ['students' => $students]);
    }

    public function print(Request $request): Response
    {
        if ($this->isPengawas($request)) {
            $supervisors = $this->resolveSupervisors($request);

            $pdf = Pdf::loadView('admin.student-cards.print-pengawas', ['supervisors' => $supervisors])
                ->setPaper('a4', 'landscape');

            return $pdf->download('kartu-login-pengawas.pdf');
        }

        $students = $this->resolveStudents($request);

        $pdf = Pdf::loadView('admin.student-cards.print', ['students' => $students])
            ->setPaper('a4', 'landscape');

        return $pdf->download('kartu-login-peserta.pdf');
    }

    private function isPengawas(Request $request): bool
    {
        return $request->string('type')->toString() === 'pengawas';
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
            ->with('user')
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
