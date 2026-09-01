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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $classrooms = Classroom::query()->orderBy('name')->get();

        // Preload kelas-eksklusif per mapel untuk classroom-picker halaman
        // Tambah Guru Mapel. Mapel dipilih via dropdown setelah halaman dimuat,
        // jadi exclusion di-batch untuk SEMUA mapel sekali saja (satu query)
        // lalu dipilih reaktif di sisi klien saat dropdown berubah (opsi
        // preload/No-AJAX: sekolah hanya ~8 mapel, payload kecil, tanpa delay).
        // Gurunya masih baru (belum ada id), jadi tidak ada guru yang dikecualikan.
        $exclusionsBySubject = $this->allExclusionsBySubject();

        return view('admin.guru-mapels.create', compact('subjects', 'classrooms', 'exclusionsBySubject'));
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

            // Penugasan awal OPSIONAL saat create: bila mapel dipilih, buat
            // penugasan mapel berikut kelas (classroom_ids) yang dipilih admin.
            // Kalau kelas kosong, tetap buat penugasan mapel saja.
            if ($request->filled('subject_id')) {
                $this->setSubjectClassrooms(
                    $guruMapel,
                    (int) $request->integer('subject_id'),
                    collect($request->input('classroom_ids', []))
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all(),
                );
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

        // Baris penugasan read-only: 1 baris PER MAPEL. Kelas untuk mapel tsb
        // dikumpulkan jadi satu daftar id agar tampilan bisa meringkas tingkat
        // penuh (mis. "XII (Semua Kelas)") via Classroom::summarizeTargets().
        $assignments = collect();
        foreach ($guruMapel->classScopeBySubject() as $subjectId => $rooms) {
            $assignments->push([
                'subject' => $subjectModels->get($subjectId),
                'classroom_ids' => collect($rooms)->pluck('id')->values(),
            ]);
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
     * Halaman kelola penugasan (mapel + kelas) seorang guru mapel. Kelas yang
     * menjadi cakupan akses disimpan eksplisit pada classroom_id tiap baris
     * penugasan; admin dapat menambah mapel lalu memilih kelas per mapel.
     */
    public function editAssignments(GuruMapel $guruMapel): View
    {
        $guruMapel->load(['user']);

        $rows = $guruMapel->assignments()
            ->with(['subject', 'classroom'])
            ->orderBy('subject_id')
            ->get();

        // Kelompokkan per mapel (subject) karena satu mapel dapat punya banyak
        // kelas (satu baris per mapel-kelas).
        $assignments = $rows
            ->groupBy('subject_id')
            ->map(function ($group) {
                /** @var Collection<int, TeacherSubjectClassAssignment> $group */
                $first = $group->first();

                return [
                    'subject' => $first->subject,
                    'classroom_ids' => $group
                        ->filter(fn ($a) => $a->classroom_id !== null)
                        ->pluck('classroom_id')
                        ->unique()
                        ->values(),
                ];
            })
            ->values();

        $subjects = Subject::query()->orderBy('name')->get();
        $classrooms = Classroom::query()->orderBy('name')->get();
        $classScope = $guruMapel->classScopeBySubject();
        $assignedSubjectIds = $rows->pluck('subject_id')->unique()->values();

        // Kelas yang diampu guru LAIN per mapel — untuk disable + keterangan
        // pada classroom-picker (eksklusivitas per kombinasi mapel,kelas).
        // Map: subject_id => [ classroom_id => "Guru <nama>" ].
        // Satu query batch untuk SEMUA mapel di halaman ini (hindari N+1).
        $exclusiveBySubject = [];
        $takenRows = TeacherSubjectClassAssignment::query()
            ->whereIn('subject_id', $assignedSubjectIds)
            ->where('guru_mapel_id', '!=', $guruMapel->id)
            ->whereNotNull('classroom_id')
            ->with(['classroom', 'guruMapel.user'])
            ->get();

        foreach ($takenRows as $row) {
            $exclusiveBySubject[(int) $row->subject_id][(int) $row->classroom_id] = $row->guruMapel?->user?->name ?? 'Guru lain';
        }

        return view('admin.guru-mapels.assignments', compact(
            'guruMapel', 'assignments', 'subjects', 'classrooms', 'classScope', 'assignedSubjectIds', 'exclusiveBySubject'
        ));
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

    /**
     * Simpan daftar kelas untuk satu mapel tertentu milik guru. Mengganti
     * baris kelas mapel tersebut secara utuh (delete + re-insert) agar
     * konsisten dengan indeks unik (guru, mapel, kelas). Bila tidak ada kelas
     * dipilih, penugasan mapel tetap dipertahankan sebagai baris mapel saja
     * (classroom_id null).
     */
    public function updateAssignmentClassrooms(Request $request, GuruMapel $guruMapel, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'classroom_ids' => ['array'],
            'classroom_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $classroomIds = collect($validated['classroom_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->setSubjectClassrooms($guruMapel, (int) $subject->id, $classroomIds);

        return redirect()->route('admin.guru-mapels.assignments.edit', $guruMapel)
            ->with('success', 'Cakupan kelas untuk mapel berhasil disimpan.');
    }

    /**
     * Hapus semua penugasan (baris mapel-kelas) untuk satu mapel dari guru.
     * Dipakai tombol "Hapus" pada halaman Kelola Mapel agar seluruh kelas dari
     * mapel tersebut ikut terhapus, bukan hanya satu baris.
     */
    public function destroySubjectAssignment(GuruMapel $guruMapel, Subject $subject): RedirectResponse
    {
        $guruMapel->assignments()->where('subject_id', (int) $subject->id)->delete();

        return redirect()->route('admin.guru-mapels.assignments.edit', $guruMapel)
            ->with('success', 'Penugasan mapel berhasil dihapus.');
    }

    public function destroyAssignment(TeacherSubjectClassAssignment $assignment): RedirectResponse
    {
        $guru = $assignment->guru_mapel_id;
        $assignment->delete();

        return redirect()->route('admin.guru-mapels.assignments.edit', $guru)
            ->with('success', 'Penugasan mapel berhasil dihapus.');
    }

    /**
     * Atur kelas untuk satu mapel milik guru: hapus semua baris mapel tsb,
     * lalu tulis ulang satu baris per kelas. Dengan daftar kelas kosong,
     * pertahankan satu baris mapel-saja (classroom_id null) agar penugasan
     * mapel tidak hilang.
     *
     * Menegakkan eksklusivitas per kombinasi (mapel, kelas): kelas yang sudah
     * diampu guru LAIN untuk mapel yang sama tidak boleh diambil lagi.
     *
     * @param  array<int, int>  $classroomIds
     */
    private function setSubjectClassrooms(GuruMapel $guruMapel, int $subjectId, array $classroomIds): void
    {
        $subjectName = (string) (Subject::query()->find($subjectId)?->name ?? 'Mapel #'.$subjectId);

        if (! empty($classroomIds)) {
            $taken = $this->exclusiveClassroomsBySubject($subjectId, $guruMapel->id, $classroomIds);
            if ($taken->isNotEmpty()) {
                // Pesan ringkas 1 baris (garda terakhir untuk manipulasi manual).
                // Kelas yang bentrok seharusnya sudah nonaktif di frontend.
                throw ValidationException::withMessages([
                    'classroom_ids' => "Sebagian kelas yang dipilih sudah diampu guru lain untuk mapel {$subjectName}. Silakan refresh halaman dan coba lagi.",
                ]);
            }
        }

        DB::transaction(function () use ($guruMapel, $subjectId, $classroomIds) {
            $guruMapel->assignments()->where('subject_id', $subjectId)->delete();

            if (empty($classroomIds)) {
                TeacherSubjectClassAssignment::create([
                    'guru_mapel_id' => $guruMapel->id,
                    'subject_id' => $subjectId,
                    'classroom_id' => null,
                ]);

                return;
            }

            foreach ($classroomIds as $classroomId) {
                TeacherSubjectClassAssignment::create([
                    'guru_mapel_id' => $guruMapel->id,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                ]);
            }
        });
    }

    /**
     * Kelas yang sudah diampu guru LAIN untuk mapel tertentu (dari daftar
     * $classroomIds), untuk menegakkan eksklusivitas per (mapel, kelas).
     *
     * @param  array<int, int>  $classroomIds
     * @return Collection<int, array{classroom: Classroom, owner: string}>
     */
    private function exclusiveClassroomsBySubject(int $subjectId, int $exceptGuruId, array $classroomIds): Collection
    {
        if (empty($classroomIds)) {
            return collect();
        }

        return TeacherSubjectClassAssignment::query()
            ->where('subject_id', $subjectId)
            ->where('guru_mapel_id', '!=', $exceptGuruId)
            ->whereNotNull('classroom_id')
            ->whereIn('classroom_id', $classroomIds)
            ->with(['classroom', 'guruMapel.user'])
            ->get()
            ->map(function ($assignment) {
                return [
                    'classroom' => $assignment->classroom,
                    'owner' => (string) ($assignment->guruMapel?->user?->name ?? 'Guru lain'),
                ];
            });
    }

    /**
     * Peta subject_id => [ classroom_id => nama guru pemilik ] untuk SEMUA mapel
     * yang punya penugasan kelas di DB. Dipakai preload frontend (Tambah Guru
     * Mapel) supaya kelas yang sudah diampu guru lain tampil disabled sejak awal.
     * Satu query batch seluruh baris penugasan (hindari N+1 per mapel).
     *
     * @return array<int, array<int, string>>
     */
    private function allExclusionsBySubject(): array
    {
        $map = [];

        $rows = TeacherSubjectClassAssignment::query()
            ->whereNotNull('classroom_id')
            ->with(['guruMapel.user'])
            ->get();

        foreach ($rows as $row) {
            $map[(int) $row->subject_id][(int) $row->classroom_id] = $row->guruMapel?->user?->name ?? 'Guru lain';
        }

        return $map;
    }
}
