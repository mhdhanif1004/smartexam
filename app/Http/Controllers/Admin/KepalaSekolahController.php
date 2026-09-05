<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKepalaSekolahRequest;
use App\Http\Requests\Admin\UpdateKepalaSekolahRequest;
use App\Models\KepalaSekolah;
use App\Models\User;
use App\Services\CredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KepalaSekolahController extends Controller
{
    public function index(Request $request): View
    {
        $kepalaSekolahs = KepalaSekolah::query()
            ->with('user')
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

        return view('admin.kepala-sekolahs.index', compact('kepalaSekolahs'));
    }

    public function create(): View
    {
        return view('admin.kepala-sekolahs.create');
    }

    public function store(StoreKepalaSekolahRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $credentialGenerator = app(CredentialGenerator::class);

            $password = $request->filled('password')
                ? $request->password
                : $credentialGenerator->password();

            $email = $request->filled('email')
                ? $request->input('email')
                : $credentialGenerator->uniqueEmail($request->name);

            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => $password,
                'plain_password' => $password,
                'role' => User::ROLE_KEPALA_SEKOLAH,
                'is_active' => $request->boolean('is_active'),
            ]);

            $user->kepalaSekolah()->create([
                'nip' => $request->input('nip'),
            ]);
        });

        return redirect()->route('admin.kepala-sekolahs.index')->with('success', 'Data kepala sekolah berhasil ditambahkan.');
    }

    public function edit(KepalaSekolah $kepalaSekolah): View
    {
        $kepalaSekolah->load('user');

        return view('admin.kepala-sekolahs.edit', compact('kepalaSekolah'));
    }

    public function update(UpdateKepalaSekolahRequest $request, KepalaSekolah $kepalaSekolah): RedirectResponse
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

        $kepalaSekolah->user->update($userData);
        $kepalaSekolah->update(['nip' => $request->input('nip')]);

        return redirect()->route('admin.kepala-sekolahs.index')->with('success', 'Data kepala sekolah berhasil diperbarui.');
    }

    public function destroy(KepalaSekolah $kepalaSekolah): RedirectResponse
    {
        DB::transaction(function () use ($kepalaSekolah) {
            $kepalaSekolah->delete();
            $kepalaSekolah->user?->delete();
        });

        return redirect()->route('admin.kepala-sekolahs.index')->with('success', 'Data kepala sekolah berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu kepala sekolah untuk dihapus.');
        }

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $kepalaSekolahs = KepalaSekolah::query()->whereIn('id', $ids)->get();

            foreach ($kepalaSekolahs as $kepalaSekolah) {
                $kepalaSekolah->delete();
                $kepalaSekolah->user?->delete();
                $deleted++;
            }
        });

        return back()->with('success', "{$deleted} data kepala sekolah berhasil dihapus.");
    }
}
