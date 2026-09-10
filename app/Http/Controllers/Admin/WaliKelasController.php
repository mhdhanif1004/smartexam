<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWaliKelasRequest;
use App\Http\Requests\Admin\UpdateWaliKelasRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Models\WaliKelas;
use App\Services\CredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WaliKelasController extends Controller
{
    public function index(Request $request): View
    {
        $waliKelas = WaliKelas::query()
            ->with(['user', 'classroom'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('user_id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.wali-kelas.index', compact('waliKelas'));
    }

    public function create(): View
    {
        // Kelas yang belum punya wali kelas agar eksklusivitas 1 kelas = 1 wali
        // terlihat dari pilihan di form (kelas terpakai tidak bisa dipilih lagi).
        $availableClassrooms = Classroom::query()
            ->whereDoesntHave('waliKelas')
            ->orderBy('name')
            ->get();

        return view('admin.wali-kelas.create', compact('availableClassrooms'));
    }

    public function store(StoreWaliKelasRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $credentialGenerator = app(CredentialGenerator::class);

            $password = $request->filled('password')
                ? $request->password
                : $credentialGenerator->password();

            // Email: pakai input manual admin kalau diisi; kalau kosong,
            // generate otomatis unik dari nama (wali@gmail.com, dst).
            $email = $request->filled('email')
                ? $request->input('email')
                : $credentialGenerator->uniqueEmail($request->name);

            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_WALI_KELAS,
                'is_active' => $request->boolean('is_active'),
            ]);

            $user->waliKelas()->create([
                'classroom_id' => (int) $request->integer('classroom_id'),
            ]);
        });

        return redirect()->route('admin.wali-kelas.index')->with('success', 'Data wali kelas berhasil ditambahkan.');
    }

    public function edit(WaliKelas $waliKelas): View
    {
        $waliKelas->load(['user']);

        // Kelas bebas = semua kelas, dengan kelas milik wali ini tetap terpilih
        // (kemungkinan pindah kelas: 1 wali pindah ke kelas lain yang kosong).
        $classrooms = Classroom::query()
            ->withExists('waliKelas')
            ->orderBy('name')
            ->get();

        return view('admin.wali-kelas.edit', compact('waliKelas', 'classrooms'));
    }

    public function update(UpdateWaliKelasRequest $request, WaliKelas $waliKelas): RedirectResponse
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

        $waliKelas->user->update($userData);
        $waliKelas->update(['classroom_id' => (int) $request->integer('classroom_id')]);

        return redirect()->route('admin.wali-kelas.index')->with('success', 'Data wali kelas berhasil diperbarui.');
    }

    public function destroy(WaliKelas $waliKelas): RedirectResponse
    {
        DB::transaction(function () use ($waliKelas) {
            $waliKelas->delete();
            $waliKelas->user?->delete();
        });

        return redirect()->route('admin.wali-kelas.index')->with('success', 'Data wali kelas berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu wali kelas untuk dihapus.');
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $waliKelas = WaliKelas::query()->whereIn('id', $ids)->get();

            foreach ($waliKelas as $wali) {
                $wali->delete();
                $wali->user?->delete();
                $deleted++;
            }
        });

        return back()->with('success', "{$deleted} data wali kelas berhasil dihapus.");
    }
}
