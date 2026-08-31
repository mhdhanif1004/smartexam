<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassroomRequest;
use App\Http\Requests\Admin\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $classrooms = Classroom::query()
            ->withCount('students')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.classrooms.index', compact('classrooms'));
    }

    public function create(): View
    {
        return view('admin.classrooms.create');
    }

    public function show(Classroom $classroom): View
    {
        $students = Student::query()
            ->with('user')
            ->where('classroom_id', $classroom->id)
            ->orderBy('nisn')
            ->get();

        // Guru pengampu kelas ini diturunkan dari soal yang menargetkan kelas
        // tersebut (question_classroom), bukan lagi disimpan di pivot
        // penugasan. Bersifat read-only dan mengikuti soal terkini.
        $assignments = Question::query()
            ->with(['subject', 'creator.guruMapel'])
            ->whereHas('classrooms', fn ($query) => $query->whereKey($classroom->id))
            ->whereNotNull('created_by_user_id')
            ->get()
            ->map(fn (Question $question) => [
                'subject' => $question->subject,
                'guru' => $question->creator?->guruMapel,
            ])
            ->unique(fn ($row) => ($row['subject']?->id ?? 'null').'-'.($row['guru']?->id ?? 'null'))
            ->values();

        return view('admin.classrooms.show', compact('classroom', 'students', 'assignments'));
    }

    public function store(StoreClassroomRequest $request): RedirectResponse
    {
        $classroom = Classroom::create($request->validated());

        return redirect()->route('admin.classrooms.index')
            ->with('success', "Kelas {$classroom->name} berhasil ditambahkan.");
    }

    public function edit(Classroom $classroom): View
    {
        return view('admin.classrooms.edit', compact('classroom'));
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $newName = $request->validated()['name'];

        $this->renameClass($classroom, $newName);

        return redirect()->route('admin.classrooms.index')
            ->with('success', "Kelas {$classroom->name} berhasil diperbarui.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $studentsCount = Student::query()->where('classroom_id', $classroom->id)->count();

        if ($studentsCount > 0) {
            return redirect()->route('admin.classrooms.index')
                ->with('error', "Tidak bisa dihapus, masih ada {$studentsCount} siswa di kelas ini. Pindahkan siswa itu ke kelas lain dulu.");
        }

        $name = $classroom->name;
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')
            ->with('success', "Kelas {$name} berhasil dihapus.");
    }

    /**
     * Ubah nama kelas sekaligus sinkronkan class_name semua siswa yang
     * terhubung via classroom_id, supaya tidak ada siswa yang "yatim" (nama
     * kelasnya tidak match dengan master data kelas).
     */
    private function renameClass(Classroom $classroom, string $newName): void
    {
        DB::transaction(function () use ($classroom, $newName) {
            $classroom->update(['name' => $newName]);

            Student::query()->where('classroom_id', $classroom->id)
                ->update(['class_name' => $newName]);
        });
    }
}
