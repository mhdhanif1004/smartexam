<?php

namespace App\Http\Controllers\GuruMapel;

use App\Exports\GradesExport;
use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Traits\ScopesGuruMapel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeController extends Controller
{
    use ScopesGuruMapel;

    public function index(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;
        $gradeType = $request->filled('grade_type') ? $request->string('grade_type')->toString() : Grade::TYPE_TUGAS;
        $title = $request->string('title')->toString();

        $students = collect();
        $savedGrades = collect();
        $chartData = [];

        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $students = Student::query()
                ->with('user')
                ->where('classroom_id', $classroomId)
                ->orderBy('nisn')
                ->get();

            $savedGrades = Grade::query()
                ->where('guru_mapel_id', $guru->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->where('grade_type', $gradeType)
                ->where('title', $title !== '' ? $title : null)
                ->get()
                ->keyBy('student_id');

            $typeAgg = Grade::query()
                ->select('grade_type', DB::raw('AVG(score) as avg_score'), DB::raw('MAX(score) as max_score'), DB::raw('MIN(score) as min_score'))
                ->where('guru_mapel_id', $guru->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->groupBy('grade_type')
                ->orderBy('grade_type')
                ->get();

            foreach ($typeAgg as $row) {
                $chartData[] = [
                    'type' => Grade::TYPES[$row->grade_type] ?? $row->grade_type,
                    'average' => round((float) $row->avg_score, 2),
                    'highest' => round((float) $row->max_score, 2),
                    'lowest' => round((float) $row->min_score, 2),
                ];
            }
        }

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $gradeTypes = Grade::TYPES;
        $guruId = $guru->id;

        return view('guru_mapel.grades.index', compact(
            'guru', 'guruId', 'subjects', 'classrooms', 'subjectId', 'classroomId',
            'gradeType', 'title', 'students', 'savedGrades', 'validSelection', 'gradeTypes', 'chartData',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'grade_type' => ['required', Rule::in(array_keys(Grade::TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $classroomId = (int) $validated['classroom_id'];
        $gradeType = $validated['grade_type'];
        $title = $request->filled('title') ? trim((string) $request->input('title')) : '';
        $title = $title !== '' ? $title : null;

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $studentIds = Student::query()
            ->where('classroom_id', $classroomId)
            ->pluck('id');

        $scores = $request->input('score', []);
        $notes = $request->input('note', []);

        $validator = Validator::make($request->all(), [
            'score' => ['required', 'array'],
            'score.*' => ['required', 'numeric', 'between:0,100.00'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($scores, $studentIds) {
            foreach (array_keys($scores) as $studentId) {
                if (! $studentIds->contains((int) $studentId)) {
                    $validator->errors()->add('score', 'Terdapat siswa yang bukan bagian dari kelas ini.');

                    return;
                }
            }
        })->validate();

        foreach ($scores as $studentId => $score) {
            Grade::updateOrCreate(
                [
                    'guru_mapel_id' => $guru->id,
                    'student_id' => (int) $studentId,
                    'subject_id' => $subjectId,
                    'classroom_id' => $classroomId,
                    'grade_type' => $gradeType,
                ],
                [
                    'title' => $title,
                    'score' => $score,
                    'note' => $notes[(int) $studentId] ?? null,
                ]
            );
        }

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    public function students(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $students = collect();
        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $students = Student::query()
                ->with('user')
                ->where('classroom_id', $classroomId)
                ->orderBy('nisn')
                ->get();
        }

        return view('guru_mapel.grades.students', compact(
            'guru', 'subjects', 'classrooms', 'subjectId', 'classroomId', 'students', 'validSelection',
        ));
    }

    public function studentHistory(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');
        $studentId = (int) $request->integer('student_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        $student = Student::query()
            ->with('user')
            ->whereKey($studentId)
            ->where('classroom_id', $classroomId)
            ->firstOrFail();

        $grades = Grade::query()
            ->with(['classroom', 'subject'])
            ->where('guru_mapel_id', $guru->id)
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->orderByDesc('grade_type')
            ->orderByDesc('created_at')
            ->get();

        $subject = $grades->first()?->subject ?? $guru->subjects()->whereKey($subjectId)->first();

        return view('guru_mapel.grades.student-history', compact('guru', 'subject', 'student', 'grades'));
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $rows = $this->gradeRows($request);

        return Excel::download(new GradesExport($rows), 'daftar-nilai.xlsx');
    }

    public function exportPdf(Request $request): Response
    {
        $rows = $this->gradeRows($request);
        $subject = $rows->first()?->subject;
        $classroom = $rows->first()?->classroom;

        $pdf = Pdf::loadView('guru_mapel.grades.print', compact('rows', 'subject', 'classroom'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('daftar-nilai.pdf');
    }

    /**
     * Ambil seluruh nilai milik guru pada kombinasi mapel-kelas yang diampu
     * (di-otorisasi). Dipakai bersama export Excel & PDF.
     *
     * @return Collection<int, Grade>
     */
    private function gradeRows(Request $request): Collection
    {
        $guru = $this->currentGuru();

        $subjectId = (int) $request->integer('subject_id');
        $classroomId = (int) $request->integer('classroom_id');

        if (! $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId)) {
            abort(403, 'Anda tidak mengampu kombinasi mapel-kelas ini.');
        }

        return Grade::query()
            ->with(['student.user', 'classroom', 'subject'])
            ->where('guru_mapel_id', $guru->id)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->orderByDesc('created_at')
            ->get();
    }
}
