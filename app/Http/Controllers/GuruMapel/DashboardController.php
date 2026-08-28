<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Question;
use App\Traits\ScopesGuruMapel;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesGuruMapel;

    public function __invoke(): View
    {
        $guru = $this->currentGuru();

        $guru->load(['assignments.subject', 'assignments.classroom.students']);

        $assignmentCount = $guru->assignments->count();
        $subjectCount = $guru->assignments->pluck('subject_id')->unique()->count();
        $classCount = $guru->assignments->pluck('classroom_id')->unique()->count();

        $assignments = $guru->assignments
            ->map(fn ($assignment) => [
                'subject' => $assignment->subject,
                'classroom' => $assignment->classroom,
                'student_count' => $assignment->classroom?->students?->count() ?? 0,
            ]);

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
            'recentGrades',
            'totalQuestions',
            'gradesThisMonth',
        ));
    }
}
