<?php

namespace App\Traits;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;

trait ScopesGuruMapel
{
    /**
     * Profil guru mapel dari pengguna yang sedang login. Melakukan
     * aborsi 403 bila akun pengguna bukan guru mapel atau belum punya
     * profil guru mapel (harus sudah ditugaskan oleh admin).
     */
    protected function currentGuru(): GuruMapel
    {
        $guru = auth()->user()?->guruMapel;

        abort_unless($guru instanceof GuruMapel, 403, 'Anda tidak terdaftar sebagai guru mapel.');

        return $guru;
    }

    /**
     * Mapel yang diampu guru ini, diurutkan berdasarkan nama.
     *
     * @return Collection<int, Subject>
     */
    protected function ampuSubjects(GuruMapel $guru)
    {
        return Subject::query()
            ->whereIn('id', $guru->ampuSubjectIds())
            ->orderBy('name')
            ->get();
    }

    /**
     * Kelas yang diampu guru untuk mapel tertentu, diurutkan berdasarkan nama.
     *
     * @return Collection<int, Classroom>
     */
    protected function ampuClassrooms(GuruMapel $guru, int $subjectId)
    {
        return Classroom::query()
            ->whereIn('id', $guru->ampuClassroomIds($subjectId))
            ->orderBy('name')
            ->get();
    }
}
