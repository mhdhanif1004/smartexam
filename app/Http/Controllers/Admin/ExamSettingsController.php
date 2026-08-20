<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamSettingsController extends Controller
{
    public function edit(): View
    {
        $setting = ExamSetting::current() ?? new ExamSetting;

        return view('admin.exam-settings.edit', ['setting' => $setting]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_supervisors_per_room' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $setting = ExamSetting::current();

        if ($setting === null) {
            $setting = ExamSetting::create($validated);
        } else {
            $setting->fill($validated);
            $setting->save();
        }

        return redirect()
            ->route('admin.exam-settings.edit')
            ->with('success', 'Pengaturan ujian berhasil disimpan.');
    }
}
