<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuruMapelRequest;
use App\Http\Requests\Admin\UpdateGuruMapelRequest;
use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuruMapelController extends Controller
{
    public function index(Request $request): View
    {
        $guruMapels = GuruMapel::query()
            ->with(['user', 'subjects', 'classrooms'])
            ->withCount('assignments')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderBy('user_id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.guru-mapels.index', compact('guruMapels'));
    }

    public function create(): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $classrooms = Classroom::query()->orderBy('name')->get();

        return view('admin.guru-mapels.create', compact('subjects', 'classrooms'));
    }

    public function store(StoreGuruMapelRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $credentialGenerator = app(CredentialGenerator::class);

            $password = $request->filled('password')
                ? $request->password
                : $credentialGenerator->password();

            // Email: pakai input manual admin kalau diisi; kalau kosong,
            // generate otomatis unik dari nama (guru@gmail.com, dst).
            $email = $request->filled('email')
                ? $request->input('email')
                : $credentialGenerator->uniqueEmail($request->name);

            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_GURU_MAPEL,
                'is_active' => $request->boolean('is_active'),
            ]);

            $guruMapel = $user->guruMapel()->create([
                'nip' => $request->input('nip'),
            ]);

            // Penugasan mapel-kelas OPSIONAL saat create: langsung buat bila
            // mapel & kelas dipilih, kalau tidak guru di-assign belakangan lewat
            // halaman "Kelola Penugasan".
            if ($request->filled('subject_id') && $request->has('classroom_ids')) {
                $subjectId = (int) $request->integer('subject_id');

                foreach (collect($request->input('classroom_ids'))->map(fn ($id) => (int) $id)->unique() as $classroomId) {
                    TeacherSubjectClassAssignment::firstOrCreate([
                        'guru_mapel_id' => $guruMapel->id,
                        'subject_id' => $subjectId,
                        'classroom_id' => $classroomId,
                    ]);
                }
            }
        });

        return redirect()->route('admin.guru-mapels.index')->with('success', 'Data guru mapel berhasil ditambahkan.');
    }

    public function show(GuruMapel $guruMapel): View
    {
        $guruMapel->load(['user', 'assignments.subject', 'assignments.classroom']);

        $assignments = $guruMapel->assignments;
        $subjects = Subject::query()->orderBy('name')->get();
        $classrooms = Classroom::query()->orderBy('name')->get();

        return view('admin.guru-mapels.show', compact('guruMapel', 'assignments', 'subjects', 'classrooms'));
    }

    public function edit(GuruMapel $guruMapel): View
    {
        return view('admin.guru-mapels.edit', compact('guruMapel'));
    }

    public function update(UpdateGuruMapelRequest $request, GuruMapel $guruMapel): RedirectResponse
    {
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $userData['password'] = $request->password;
            $userData['plain_password'] = $request->password;
        }

        $guruMapel->user->update($userData);
        $guruMapel->update(['nip' => $request->input('nip')]);

        return redirect()->route('admin.guru-mapels.index')->with('success', 'Data guru mapel berhasil diperbarui.');
    }

    public function destroy(GuruMapel $guruMapel): RedirectResponse
    {
        DB::transaction(function () use ($guruMapel) {
            $guruMapel->delete();
            $guruMapel->user?->delete();
        });

        return redirect()->route('admin.guru-mapels.index')->with('success', 'Data guru mapel berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu guru mapel untuk dihapus.');
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $gurus = GuruMapel::query()->whereIn('id', $ids)->get();

            foreach ($gurus as $guru) {
                $guru->delete();
                $guru->user?->delete();
                $deleted++;
            }
        });

        return back()->with('success', "{$deleted} data guru mapel berhasil dihapus.");
    }

    /**
     * Halaman kelola penugasan (mapel -> kelas) seorang guru mapel.
     */
    public function editAssignments(GuruMapel $guruMapel): View
    {
        $guruMapel->load(['user', 'assignments.subject', 'assignments.classroom']);

        $assignments = $guruMapel->assignments;
        $subjects = Subject::query()->orderBy('name')->get();
        $classrooms = Classroom::query()->orderBy('name')->get();

        // Peta subject_id => [classroom_id] agar checkbox kelas yang sudah
        // diassign untuk mapel tertentu bisa langsung tercentang di form.
        $classroomIdsBySubject = $assignments
            ->groupBy('subject_id')
            ->map(fn ($group) => $group->pluck('classroom_id')->map(fn ($id) => (int) $id)->values()->all())
            ->toArray();

        return view('admin.guru-mapels.assignments', compact('guruMapel', 'assignments', 'subjects', 'classrooms', 'classroomIdsBySubject'));
    }

    /**
     * Simpan penugasan bulk untuk satu mapel: semua kelas yang dicentang
     * disinkronkan sekaligus (create yang baru, hapus yang di-uncheck).
     * Unik per kombinasi guru+mapel+kelas tetap terjaga lewat firstOrCreate
     * setelah menghapus yang tidak lagi dipilih, sehingga submit ulang dengan
     * set yang sama tidak menimbulkan duplikat.
     */
    public function storeAssignment(Request $request, GuruMapel $guruMapel): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $classroomIds = collect($validated['classroom_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($guruMapel, $subjectId, $classroomIds) {
            // Hapus penugasan mapel ini untuk kelas yang tidak lagi dicentang.
            TeacherSubjectClassAssignment::query()
                ->where('guru_mapel_id', $guruMapel->id)
                ->where('subject_id', $subjectId)
                ->whereNotIn('classroom_id', $classroomIds)
                ->delete();

            // Buat untuk kelas yang baru dicentang (abaikan yang sudah ada).
            foreach ($classroomIds as $classroomId) {
                TeacherSubjectClassAssignment::firstOrCreate([
                    'guru_mapel_id' => $guruMapel->id,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                ]);
            }
        });

        return redirect()->route('admin.guru-mapels.assignments.edit', $guruMapel)
            ->with('success', 'Penugasan mapel-kelas berhasil disimpan.');
    }

    public function destroyAssignment(TeacherSubjectClassAssignment $assignment): RedirectResponse
    {
        $guru = $assignment->guru_mapel_id;
        $assignment->delete();

        return redirect()->route('admin.guru-mapels.assignments.edit', $guru)
            ->with('success', 'Penugasan mapel-kelas berhasil dihapus.');
    }
}
