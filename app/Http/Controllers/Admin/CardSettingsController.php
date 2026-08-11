<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CardSettingsController extends Controller
{
    public function edit(): View
    {
        $setting = CardSetting::current() ?? new CardSetting;

        return view('admin.card-settings.edit', ['setting' => $setting]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_sekolah' => ['nullable', 'string', 'max:255'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'nama_kepala_sekolah' => ['nullable', 'string', 'max:255'],
            'jabatan_kepala_sekolah' => ['nullable', 'string', 'max:255'],
            'logo_kiri' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_kanan' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_logo_kiri' => ['nullable', 'boolean'],
            'hapus_logo_kanan' => ['nullable', 'boolean'],
        ]);

        $setting = CardSetting::current();

        if ($setting === null) {
            $setting = CardSetting::create([
                'nama_sekolah' => $validated['nama_sekolah'] ?? 'SmartExam',
                'nama_kepala_sekolah' => $validated['nama_kepala_sekolah'] ?? null,
                'jabatan_kepala_sekolah' => $validated['jabatan_kepala_sekolah'] ?? 'Kepala Sekolah',
                'tempat' => $validated['tempat'] ?? null,
            ]);
        }

        $setting->fill([
            'nama_sekolah' => $validated['nama_sekolah'] ?? null,
            'tempat' => $validated['tempat'] ?? null,
            'nama_kepala_sekolah' => $validated['nama_kepala_sekolah'] ?? null,
            'jabatan_kepala_sekolah' => $validated['jabatan_kepala_sekolah'] ?? 'Kepala Sekolah',
        ]);

        if ($request->hasFile('logo_kiri')) {
            $setting->logo_kiri_path = $this->storeLogo($request->file('logo_kiri'));
        } elseif (($validated['hapus_logo_kiri'] ?? false) === true) {
            $this->deleteLogo($setting->logo_kiri_path);
            $setting->logo_kiri_path = null;
        }

        if ($request->hasFile('logo_kanan')) {
            $setting->logo_kanan_path = $this->storeLogo($request->file('logo_kanan'));
        } elseif (($validated['hapus_logo_kanan'] ?? false) === true) {
            $this->deleteLogo($setting->logo_kanan_path);
            $setting->logo_kanan_path = null;
        }

        $setting->save();

        return redirect()
            ->route('admin.card-settings.edit')
            ->with('success', 'Pengaturan kartu login berhasil disimpan.');
    }

    /**
     * @return UploadedFile
     */
    private function storeLogo(object $file): string
    {
        return $file->storeAs(
            'card-settings',
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
