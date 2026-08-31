<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuruMapelRequest;
use App\Http\Requests\Admin\UpdateGuruMapelRequest;
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
            ->with(['user', 'subjects'])
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

        return view('admin.guru-mapels.create', compact('subjects'));
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

            // Penugasan mapel OPSIONAL saat create: langsung buat bila mapel
            // dipilih, kalau tidak guru di-assign belakangan lewat halaman
            // "Kelola Penugasan".
            if ($request->filled('subject_id')) {
                TeacherSubjectClassAssignment::firstOrCreate([
                    'guru_mapel_id' => $guruMapel->id,
                    'subject_id' => (int) $request->integer('subject_id'),
                ]);
            }
        });

        return redirect()->route('admin.guru-mapels.index')->with('success', 'Data guru mapel berhasil ditambahkan.');
    }

    public function show(GuruMapel $guruMapel): View
    {
        $guruMapel->load(['user', 'subjects']);

        $classScope = $guruMapel->classScopeBySubject();

        $subjectIds = collect($classScope)->keys()->merge($guruMapel->ampuSubjectIds())->unique()->values();

        $subjectModels = Subject::query()->whereIn('id', $subjectIds)->get()->keyBy('id');

        // Baris penugasan read-only: (mapel, kelas) diturunkan dari soal yang
        // dibuat guru, bukan lagi diisi manual oleh admin.
        $assignments = collect();
        foreach ($guruMapel->classScopeBySubject() as $subjectId => $rooms) {
            foreach ($rooms as $room) {
                $assignments->push([
                    'subject' => $subjectModels->get($subjectId),
                    'classroom' => (object) ['id' => $room['id'], 'name' => $room['name']],
                ]);
            }
        }

        return view('admin.guru-mapels.show', compact('guruMapel', 'assignments'));
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
     * Halaman kelola penugasan (mapel saja) seorang guru mapel. Kelas yang
     * menjadi cakupan akses tidak lagi diisi manual, melainkan diturunkan dari
     * soal yang dibuat guru (kelas target pada question_classroom).
     */
    public function editAssignments(GuruMapel $guruMapel): View
    {
        $guruMapel->load(['user', 'assignments.subject']);

        $assignments = $guruMapel->assignments;
        $subjects = Subject::query()->orderBy('name')->get();
        $classScope = $guruMapel->classScopeBySubject();

        return view('admin.guru-mapels.assignments', compact('guruMapel', 'assignments', 'subjects', 'classScope'));
    }

    /**
     * Tambah penugasan mapel untuk seorang guru. Unik per kombinasi
     * guru+mapel dijaga lewat firstOrCreate sehingga submit ulang tidak
     * menimbulkan duplikat.
     */
    public function storeAssignment(Request $request, GuruMapel $guruMapel): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        TeacherSubjectClassAssignment::firstOrCreate([
            'guru_mapel_id' => $guruMapel->id,
            'subject_id' => (int) $validated['subject_id'],
        ]);

        return redirect()->route('admin.guru-mapels.assignments.edit', $guruMapel)
            ->with('success', 'Penugasan mapel berhasil disimpan.');
    }

    public function destroyAssignment(TeacherSubjectClassAssignment $assignment): RedirectResponse
    {
        $guru = $assignment->guru_mapel_id;
        $assignment->delete();

        return redirect()->route('admin.guru-mapels.assignments.edit', $guru)
            ->with('success', 'Penugasan mapel berhasil dihapus.');
    }
}
