<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Subject;
use App\Traits\ScopesGuruMapel;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesGuruMapel;

    public function __invoke(): View
    {
        $guru = $this->currentGuru();

        $subjectModels = Subject::query()
            ->whereIn('id', $guru->ampuSubjectIds())
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Daftar lengkap mapel yang diampu guru ini, berikut jumlah kelas yang
        // terpetakan per mapel (diturunkan dari pivot soal -> kelas).
        $classScope = $guru->classScopeBySubject();

        $subjects = $subjectModels
            ->map(fn (Subject $subject) => [
                'subject' => $subject,
                'class_count' => count($classScope[$subject->id] ?? []),
            ])
            ->values();

        // Cakupan kelas per mapel diturunkan live dari soal yang dibuat guru
        // (kelas target pada pivot question_classroom). Baris penugasan =
        // (mapel, kelas) beserta jumlah siswa; mapel tanpa soal tidak punya
        // kelas sehingga tidak muncul.
        $assignments = collect();
        foreach ($guru->classScopeBySubject() as $subjectId => $rooms) {
            $subject = $subjectModels->get($subjectId);

            $classroomModels = Classroom::query()
                ->whereIn('id', array_column($rooms, 'id'))
                ->withCount('students')
                ->get()
                ->keyBy('id');

            foreach ($rooms as $room) {
                $classroom = $classroomModels->get($room['id']);
                $assignments->push([
                    'subject' => $subject,
                    'classroom' => $classroom,
                    'student_count' => $classroom?->students_count ?? 0,
                ]);
            }
        }

        $assignmentCount = $assignments->count();
        $subjectCount = $assignments->pluck('subject.id')->filter()->unique()->count();
        $classCount = $assignments->pluck('classroom.id')->filter()->unique()->count();

        $recentGrades = Grade::query()
            ->with(['classroom', 'subject', 'student.user'])
            ->where('guru_mapel_id', $guru->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $totalQuestions = Question::query()
            ->where('created_by_user_id', $guru->user_id)
            ->count();

        $gradesThisMonth = Grade::query()
            ->where('guru_mapel_id', $guru->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        return view('guru_mapel.dashboard', compact(
            'guru',
            'assignmentCount',
            'subjectCount',
            'classCount',
            'assignments',
            'subjects',
            'recentGrades',
            'totalQuestions',
            'gradesThisMonth',
        ));
    }
}
