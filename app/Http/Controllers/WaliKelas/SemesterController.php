<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Traits\ResolvesSelectedSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    use ResolvesSelectedSemester;

    /**
     * Simpan semester pilihan user ke session.
     *
     * Berlaku GLOBAL untuk semua halaman Wali Kelas sampai user ganti lagi.
     * `redirect()->back()` supaya user tidak terlempar ke dashboard.
     */
    public function setSemester(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ]);

        session([self::SEMESTER_SESSION_KEY => (int) $validated['semester_id']]);

        return redirect()->back();
    }
}
