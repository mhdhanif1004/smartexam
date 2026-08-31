<?php

namespace App\Traits;

use App\Models\Classroom;
use App\Models\ExamSchedule;
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

    /**
     * Satu-satunya jalur masuk ke halaman detail jadwal ujian guru (baik
     * hasil CBT maupun absensi). Menjamin jadwal hanya dapat diakses bila
     * guru benar-benar mengampu kombinasi (mapel, kelas) jadwal tersebut;
     * selain itu aborsi 403. Dengan begini isolasi akses antar guru
     * dipusatkan di satu helper, tidak diduplikasi per controller.
     */
    protected function resolveAmpuSchedule(GuruMapel $guru, int $scheduleId): ExamSchedule
    {
        $schedule = ExamSchedule::query()
            ->with(['subject', 'examPeriod'])
            ->findOrFail($scheduleId);

        $classroomId = Classroom::query()->where('name', $schedule->class_name)->value('id');

        abort_unless($classroomId !== null && $guru->isAmpu(subjectId: $schedule->subject_id, classroomId: $classroomId), 403);

        return $schedule;
    }
}
