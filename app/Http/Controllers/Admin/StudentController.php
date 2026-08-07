<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $students = Student::query()
            ->with(['user', 'room'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($builder) use ($search) {
                    $builder->where('nisn', 'like', "%{$search}%")
                        ->orWhere('class_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->orderBy('class_name')
            ->orderBy('nisn')
            ->paginate(10)
            ->withQueryString();

        $classes = Student::query()->distinct()->orderBy('class_name')->pluck('class_name');

        return view('admin.students.index', compact('students', 'classes'));
    }

    public function create(): View
    {
        return view('admin.students.create', ['classes' => $this->masterClasses()]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $generator = app(CredentialGenerator::class);

            $password = $request->filled('password')
                ? $request->password
                : $generator->password();

            $user = User::create([
                'name' => $request->name,
                'username' => $request->filled('username')
                    ? $request->username
                    : $generator->username(),
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_PESERTA,
                'is_active' => $request->boolean('is_active'),
            ]);

            $user->student()->create([
                'nisn' => $request->nisn,
                'class_name' => $request->class_name,
            ]);
        });

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function edit(Student $student): View
    {
        return view('admin.students.edit', compact('student') + ['classes' => $this->masterClasses()]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $userData = [
                'name' => $request->name,
                'is_active' => $request->boolean('is_active'),
            ];

            if ($request->filled('password')) {
                $userData['password'] = $request->password;
                $userData['plain_password'] = $request->password;
            }

            $student->user->update($userData);

            $student->update([
                'nisn' => $request->nisn,
                'class_name' => $request->class_name,
            ]);
        });

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student) {
            $student->delete();
            $student->user?->delete();
        });

        return redirect()->route('admin.students.index')->with('success', 'Data siswa berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu siswa untuk dihapus.');
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $students = Student::query()->whereIn('id', $ids)->get();

            foreach ($students as $student) {
                $student->delete();
                $student->user?->delete();
                $deleted++;
            }
        });

        return back()->with('success', "{$deleted} data siswa berhasil dihapus.");
    }

    /**
     * Daftar kelas master yang dipakai dropdown form Tambah/Edit Siswa.
     *
     * @return Collection<int, Classroom>
     */
    private function masterClasses(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
    }
}
