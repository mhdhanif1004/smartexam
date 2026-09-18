<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Services\ActivityLogger;
use App\Services\QuestionImageOptimizer;
use App\Services\QuestionWeightService;
use App\Traits\BuildsQuestionPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    use BuildsQuestionPayload;

    public function __construct(
        private readonly QuestionImageOptimizer $imageOptimizer,
    ) {}

    public function index(Request $request): View
    {
        // Daftar mata pelajaran + jumlah soal yang cocok dengan filter saat ini.
        $subjects = Subject::query()
            ->withCount([
                'questions as questions_count' => fn (Builder $query) => $this->applyContentFilters($request, $query),
            ])
            ->orderBy('name')
            ->get();

        $hasFilter = $request->filled('search')
            || $request->filled('subject_id')
            || $request->filled('classroom_id')
            || $request->filled('type')
            || $request->filled('status');

        if ($request->filled('subject_id')) {
            // Dropdown mata pelajaran: fokus ke satu mapel saja.
            $subjects = $subjects->where('id', $request->integer('subject_id'))->values();
        } elseif ($hasFilter) {
            // Pencarian/jenis/status: sembunyikan mapel yang tidak punya hasil sama sekali.
            $subjects = $subjects->where('questions_count', '>', 0)->values();
        }

        // Diteruskan ke endpoint by-subject agar filter yang sama ikut diterapkan saat lazy-load.
        $filterQuery = http_build_query($request->only(['search', 'type', 'status', 'classroom_id']));
        $types = Question::TYPES;

        // Daftar lengkap mapel, tetap dipakai untuk dropdown filter & modal edit massal
        // (terpisah dari $subjects yang mungkin sudah disaring oleh filter aktif).
        $allSubjects = Subject::query()->orderBy('name')->get();

        $classrooms = Classroom::query()->orderBy('name')->get();

        $examTypeNames = ExamType::query()->pluck('name', 'id')->all();

        // Distinct kelas target per subject (1 query, tanpa N+1).
        $subjectClassrooms = DB::table('question_classroom')
            ->join('questions', 'questions.id', '=', 'question_classroom.question_id')
            ->join('classes', 'classes.id', '=', 'question_classroom.classroom_id')
            ->select('questions.subject_id', 'classes.name')
            ->distinct()
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('name')->sort()->values()->all());

        // Total bobot per (subject × classroom) untuk badge non-blocking di header & grup.
        $weightService = new QuestionWeightService;
        $weightChecks = [];
        foreach ($allSubjects as $subj) {
            foreach ($weightService->totalsForSubject($subj->id) as $cid => $total) {
                $weightChecks[$subj->id][$cid] = $weightService->check($subj->id, (int) $cid);
            }
        }
        // Kirim map id→name untuk label kelas di pesan warning (tanpa query tambahan di view).
        $classroomIdToName = $classrooms->pluck('name', 'id')->all();

        // Saat filter aktif, preload grouped data per mapel. Tanpa filter,
        // data diambil via AJAX saat accordion dibuka (lazy-load). Perlu setelah
        // weightChecks agar badge bobot ikut ter-render di preload.
        $preloadedGroupHtml = [];
        $preloadedQuestionIds = [];
        if ($hasFilter) {
            foreach ($subjects as $subject) {
                $questions = $this->questionsForSubject($request, $subject->id);
                $hierarchy = $this->groupQuestionsForHierarchy($questions, $examTypeNames);
                $preloadedGroupHtml[$subject->id] = view('admin.questions.partials.question-groups', [
                    'groups' => $hierarchy,
                    'subject' => $subject,
                    'search' => (string) $request->string('search')->trim(),
                    'weightChecks' => $weightChecks,
                    'classroomIdToName' => $classroomIdToName,
                ])->render();
                $preloadedQuestionIds[$subject->id] = $questions->pluck('id')->values();
            }
        }

        return view('admin.questions.index', compact(
            'subjects',
            'allSubjects',
            'types',
            'hasFilter',
            'filterQuery',
            'preloadedGroupHtml',
            'preloadedQuestionIds',
            'classrooms',
            'subjectClassrooms',
            'weightChecks',
            'classroomIdToName',
            'examTypeNames',
        ));
    }

    /**
     * Endpoint AJAX untuk lazy-load soal per mata pelajaran saat accordion dibuka.
     */
    public function bySubject(Request $request, Subject $subject): JsonResponse
    {
        $questions = $this->questionsForSubject($request, $subject->id);
        $examTypeNames = ExamType::query()->pluck('name', 'id')->all();
        $hierarchy = $this->groupQuestionsForHierarchy($questions, $examTypeNames);

        $weightService = new QuestionWeightService;
        $weightChecks = [];
        foreach ($weightService->totalsForSubject($subject->id) as $cid => $total) {
            $weightChecks[(int) $cid] = $weightService->check($subject->id, (int) $cid);
        }
        $classroomIdToName = Classroom::query()->pluck('name', 'id')->all();

        $html = view('admin.questions.partials.question-groups', [
            'groups' => $hierarchy,
            'subject' => $subject,
            'search' => (string) $request->string('search')->trim(),
            'weightChecks' => [$subject->id => $weightChecks],
            'classroomIdToName' => $classroomIdToName,
        ])->render();

        return response()->json([
            'subject_id' => $subject->id,
            'count' => $questions->count(),
            'ids' => $questions->pluck('id')->values(),
            'html' => $html,
        ]);
    }

    /**
     * Terapkan filter konten (pencarian pertanyaan, kelas target, jenis, status) ke query soal.
     */
    private function applyContentFilters(Request $request, Builder $query): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where('question_text', 'like', '%'.$request->string('search')->trim().'%');
            })
            ->when($request->filled('classroom_id'), function (Builder $query) use ($request) {
                $query->whereHas('classrooms', fn (Builder $q) => $q->whereKey($request->integer('classroom_id')));
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), function (Builder $query) use ($request) {
                $request->string('status') === 'aktif'
                    ? $query->where('is_active', true)
                    : $query->where('is_active', false);
            });
    }

    /**
     * @return Collection<int, Question>
     */
    private function questionsForSubject(Request $request, int $subjectId): Collection
    {
        $query = Question::query()
            ->with('subject', 'classrooms', 'guruMapel.user')
            ->where('subject_id', $subjectId);
        $this->applyContentFilters($request, $query);

        return $query->orderByDesc('id')->get();
    }

    /**
     * Kelompokkan soal untuk hierarki Bank Soal 5 level:
     * Guru pemilik (teacher_guru_mapel_id) → Jenis Ujian (exam_type_id) →
     * Kelas target (question_classroom) → Soal.
     *
     * Soal tanpa owner masuk bucket "Belum Ada Guru"; soal tanpa exam_type
     * masuk bucket "Belum Ditentukan" di dalam gurunya. Kepemilikan & jenis
     * HANYA label organisasi — tidak memengaruhi targeting kelas (pivot
     * question_classroom tetap sumber kebenaran saat ujian).
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<int, string>  $examTypeNames
     * @return array<int, array{guru_id: ?int, guru_name: string, types: array<int, array{exam_type_id: ?int, type_name: string, count: int, classrooms: array}>}>
     */
    private function groupQuestionsForHierarchy(Collection $questions, array $examTypeNames): array
    {
        $hierarchy = [];

        $groupedByGuru = $questions->groupBy(fn (Question $q) => $q->teacher_guru_mapel_id ?? 'unguarded');

        // Guru ber-owner dulu, bucket tanpa guru paling akhir.
        $guruKeys = $groupedByGuru->keys()->sort(function (mixed $a, mixed $b) {
            $aLast = $a === 'unguarded';
            $bLast = $b === 'unguarded';

            return $aLast === $bLast ? (is_int($a) && is_int($b) ? $a <=> $b : 0) : ($aLast ? 1 : -1);
        })->values();

        foreach ($guruKeys as $guruKey) {
            $guruQuestions = $groupedByGuru[$guruKey];
            $guru = $guruQuestions->first()->guruMapel;
            $guruName = $guruKey === 'unguarded'
                ? 'Belum Ada Guru'
                : ($guru?->user?->name ?? "Guru #{$guruKey}");

            $types = [];
            $groupedByType = $guruQuestions->groupBy(fn (Question $q) => $q->exam_type_id ?? 'undefined');

            // Jenis terurut alfabetis nama; bucket "undefined" (belum ditentukan) terakhir.
            $typeKeys = $groupedByType->keys()->sort(function (mixed $a, mixed $b) use ($examTypeNames) {
                $aLast = $a === 'undefined';
                $bLast = $b === 'undefined';

                if ($aLast !== $bLast) {
                    return $aLast ? 1 : -1;
                }

                $nameA = strtolower($examTypeNames[$a] ?? "#{$a}");
                $nameB = strtolower($examTypeNames[$b] ?? "#{$b}");

                return $nameA <=> $nameB;
            })->values();

            foreach ($typeKeys as $typeKey) {
                $typeQuestions = $groupedByType[$typeKey];

                $types[] = [
                    'exam_type_id' => $typeKey === 'undefined' ? null : (int) $typeKey,
                    'type_name' => $typeKey === 'undefined'
                        ? 'Belum Ditentukan'
                        : ($examTypeNames[$typeKey] ?? "Jenis #{$typeKey}"),
                    'count' => $typeQuestions->count(),
                    'classrooms' => $this->groupQuestionsByClassroom($typeQuestions),
                ];
            }

            $hierarchy[] = [
                'guru_id' => $guruKey === 'unguarded' ? null : (int) $guruKey,
                'guru_name' => $guruName,
                'types' => $types,
            ];
        }

        return $hierarchy;
    }

    /**
     * Kelompokkan soal berdasarkan kombinasi kelas target yang persis sama.
     * Mengembalikan array keyed by classroom_combination_key → ['classroom_ids', 'classroom_names', 'questions'].
     */
    private function groupQuestionsByClassroom(Collection $questions): array
    {
        $groups = [];
        foreach ($questions as $question) {
            $classroomIds = $question->classrooms->pluck('id')->sort()->values()->all();
            $key = implode('_', $classroomIds) ?: 'no_classroom';

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'classroom_ids' => $classroomIds,
                    'classroom_names' => $question->classrooms->sortBy('name')->pluck('name')->values()->all(),
                    'questions' => collect(),
                ];
            }
            $groups[$key]['questions']->push($question);
        }

        uasort($groups, fn (array $a, array $b) => count($b['questions']) <=> count($a['questions']));

        return $groups;
    }

    public function create(): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $classrooms = Classroom::query()->orderBy('name')->get();
        $examTypes = ExamType::query()->orderBy('sort_order')->get();
        [$gurus, $guruClassroomsBySubject] = $this->guruScopeData();

        return view('admin.questions.create', compact(
            'subjects', 'types', 'letters', 'classrooms', 'examTypes', 'gurus', 'guruClassroomsBySubject'
        ));
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $payload = $this->questionPayload($request->validated());

        [$payload['teacher_guru_mapel_id'], $payload['exam_type_id'], $payload['created_by_user_id']] = $this->ownerPayload($request);

        if ($request->hasFile('image')) {
            $payload['image_path'] = $this->imageOptimizer->optimize($request->file('image'));
        }

        $question = Question::create($payload);
        $classroomIds = $request->validated()['classroom_ids'];
        $question->classrooms()->sync($classroomIds);

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_SOAL,
            subject: $question,
            description: 'Menambahkan soal baru: '.Str::limit((string) $question->question_text, 60),
            properties: ['question_id' => $question->id, 'subject_id' => $question->subject_id, 'type' => $question->type, 'score_weight' => $question->score_weight, 'classroom_ids' => $classroomIds, 'teacher_guru_mapel_id' => $question->teacher_guru_mapel_id, 'exam_type_id' => $question->exam_type_id],
        );

        $warning = $this->weightWarningForPairs((int) $question->subject_id, $classroomIds);
        $redirect = redirect()->route('admin.questions.index')->with('success', 'Soal berhasil ditambahkan.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function edit(Question $question): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $classrooms = Classroom::query()->orderBy('name')->get();
        $examTypes = ExamType::query()->orderBy('sort_order')->get();
        [$gurus, $guruClassroomsBySubject] = $this->guruScopeData();

        return view('admin.questions.edit', compact(
            'question', 'subjects', 'types', 'letters', 'classrooms', 'examTypes', 'gurus', 'guruClassroomsBySubject'
        ));
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();

        // Capture path gambar opsi yang LAMA sebelum payload baru dibangun —
        // dipakai untuk menentukan file yang tidak lagi direferensikan.
        $oldOptionImages = $question->optionImages();

        $payload = $this->questionPayload($data);

        [$payload['teacher_guru_mapel_id'], $payload['exam_type_id'], $payload['created_by_user_id']] = $this->ownerPayload($request);

        if ($request->hasFile('image')) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = $this->imageOptimizer->optimize($request->file('image'));
        } elseif (! empty($data['remove_image'])) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = null;
        }

        $question->update($payload);
        $classroomIds = $data['classroom_ids'];
        $question->classrooms()->sync($classroomIds);

        // Cleanup orphan: hapus gambar opsi lama yang tidak lagi direferensikan
        // di options baru (termasuk saat type-change / opsi dihapus / gambar diganti).
        $newOptionImages = Question::optionImagesFromOptions($payload['options'], $payload['type']);
        $toDelete = array_values(array_diff($oldOptionImages, $newOptionImages));
        if ($toDelete !== []) {
            Storage::disk('public')->delete($toDelete);
        }

        ActivityLogger::log(
            action: ActivityAction::UBAH_SOAL,
            subject: $question,
            description: 'Mengubah soal #'.$question->id.': '.Str::limit((string) $question->question_text, 60),
            properties: ['question_id' => $question->id, 'subject_id' => $question->subject_id, 'type' => $question->type, 'score_weight' => $question->score_weight, 'classroom_ids' => $classroomIds],
        );

        $warning = $this->weightWarningForPairs((int) $question->subject_id, $classroomIds);
        $redirect = redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diperbarui.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Data dropdown "Atas Nama Guru" beserta cakupan kelas penugasan per guru.
     *
     * @return array{0: Collection<int, GuruMapel>, 1: array<int, array<int, array<int, array{id: int, name: string}>>>}
     */
    private function guruScopeData(): array
    {
        $gurus = GuruMapel::query()
            ->with('user')
            ->get()
            ->sortBy(fn (GuruMapel $guru) => mb_strtolower((string) $guru->user?->name));

        $guruClassroomsBySubject = [];

        $assignments = TeacherSubjectClassAssignment::query()
            ->whereNotNull('classroom_id')
            ->with(['classroom' => fn ($q) => $q->orderBy('name')])
            ->get()
            ->groupBy('guru_mapel_id');

        foreach ($assignments as $guruMapelId => $rows) {
            $guru = $gurus->firstWhere('id', $guruMapelId);

            if ($guru === null) {
                continue;
            }

            foreach ($rows as $assignment) {
                if ($assignment->classroom === null) {
                    continue;
                }

                $guruClassroomsBySubject[$guru->user_id][(int) $assignment->subject_id][] = [
                    'id' => (int) $assignment->classroom->id,
                    'name' => (string) $assignment->classroom->name,
                ];
            }
        }

        return [$gurus, $guruClassroomsBySubject];
    }

    /**
     * Bangun nilai kepemilikan + jenis ujian dari request admin:
     *  - teacher_guru_mapel_id: pemilik Bank Soal (nullable).
     *  - exam_type_id: kategori jenis ujian (nullable).
     *  - created_by_user_id: disinkronkan dari guru pemilik bila pemilik
     *    dipilih dan field legacy "atas nama guru" tidak diisi, agar soal
     *    tetap muncul di halaman "Soal" guru tersebut (scopeOwnedBy).
     *
     * @return array{0: ?int, 1: ?int, 2: ?int}
     */
    private function ownerPayload(Request $request): array
    {
        $teacherGuruMapelId = $request->filled('teacher_guru_mapel_id')
            ? (int) $request->input('teacher_guru_mapel_id')
            : null;

        $createdByUserId = $request->filled('creator_user_id')
            ? (int) $request->input('creator_user_id')
            : null;

        if ($teacherGuruMapelId !== null && $createdByUserId === null) {
            $createdByUserId = GuruMapel::query()->find($teacherGuruMapelId)?->user_id;
        }

        return [
            $teacherGuruMapelId,
            $request->filled('exam_type_id') ? (int) $request->input('exam_type_id') : null,
            $createdByUserId,
        ];
    }

    public function destroy(Question $question): RedirectResponse
    {
        if ($this->questionAlreadyAnswered($question->id)) {
            return back()->with('error', 'Soal ini sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.');
        }

        $snapshotId = $question->id;
        $snapshotText = Str::limit((string) $question->question_text, 60);
        $snapshotSubjectId = $question->subject_id;
        $this->deleteQuestionMedia($question);
        $question->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_SOAL,
            description: "Menghapus soal #{$snapshotId}: {$snapshotText}",
            properties: ['question_id' => $snapshotId, 'subject_id' => $snapshotSubjectId, 'question_text' => $snapshotText],
        );

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu soal untuk dihapus.');
        }

        $usedByAnswers = ExamAnswer::query()
            ->whereIn('question_id', $ids)
            ->distinct()
            ->pluck('question_id');

        if ($usedByAnswers->isNotEmpty()) {
            $labels = $usedByAnswers->map(fn (int $id) => 'soal #'.$id)->implode(', ');

            return back()->with('error', "{$labels} sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.");
        }

        $this->deleteImageFiles($ids->all());
        $deleted = Question::query()->whereIn('id', $ids)->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_BULK_SOAL,
            description: "Hapus bulk {$deleted} soal",
            properties: ['jumlah_dihapus' => $deleted, 'ids' => $ids->values()->all()],
        );

        return back()->with('success', "{$deleted} soal berhasil dihapus.");
    }

    public function duplicate(Question $question): RedirectResponse
    {
        $question->load('classrooms');

        $payload = [
            'subject_id' => $question->subject_id,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'options' => $this->duplicateOptionImages($question),
            'answer_key' => $question->answer_key,
            'score_weight' => $question->score_weight,
            'is_active' => true,
        ];

        // Salin file gambar secara fisik ke nama baru agar duplikat memiliki
        // file independen (menghapus salah satu tidak menghapus gambar soal lain).
        if (filled($question->image_path)) {
            $duplicatePath = $this->duplicateImageFile($question->image_path);
            if ($duplicatePath !== null) {
                $payload['image_path'] = $duplicatePath;
            }
        }

        $copy = Question::create($payload);

        $copy->classrooms()->sync($question->classrooms->pluck('id'));

        ActivityLogger::log(
            action: ActivityAction::DUPLIKASI_SOAL,
            subject: $copy,
            description: "Duplikasi soal #{$question->id} → #{$copy->id}",
            properties: ['source_id' => $question->id, 'new_id' => $copy->id, 'subject_id' => $copy->subject_id],
        );

        $warning = $this->weightWarningForPairs((int) $copy->subject_id, $copy->classrooms->pluck('id')->all());
        $redirect = redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diduplikasi.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Duplikasi gambar di dalam options ke path baru secara fisik. Mengembalikan
     * options baru dengan path berbeda agar dua soal tidak share file fisik.
     */
    private function duplicateOptionImages(Question $question): ?array
    {
        $options = $question->options;

        if (! is_array($options)) {
            return $options;
        }

        $copyImage = function (mixed $option): mixed {
            if (is_array($option) && isset($option['image']) && filled($option['image'])) {
                $newPath = $this->duplicateImageFile($option['image']);

                return $newPath !== null ? [...$option, 'image' => $newPath] : $option;
            }

            return $option;
        };

        if ($question->type === Question::TYPE_MATCHING) {
            return [
                'left' => array_map($copyImage, $options['left'] ?? []),
                'right' => array_map($copyImage, $options['right'] ?? []),
            ];
        }

        // single_choice, multiple_choice, true_false (atau null)
        return array_map($copyImage, $options);
    }

    public function toggleActive(Question $question): RedirectResponse
    {
        $previous = (bool) $question->is_active;
        $question->update(['is_active' => ! $previous]);

        ActivityLogger::log(
            action: ActivityAction::TOGGLE_AKTIF_SOAL,
            subject: $question,
            description: "Soal #{$question->id} ".($question->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            properties: ['question_id' => $question->id, 'is_active' => $question->is_active, 'previous' => $previous],
        );

        $state = $question->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $warning = $this->weightWarningForPairs((int) $question->subject_id, $question->classrooms()->pluck('classes.id')->all());
        $redirect = back()->with('success', "Soal berhasil {$state}.");
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function bulkEdit(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu soal untuk diubah.');
        }

        $payload = $request->validate([
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'score_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updates = array_filter([
            'subject_id' => $payload['subject_id'] ?? null,
            'score_weight' => $payload['score_weight'] ?? null,
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : null,
        ], fn ($value) => $value !== null);

        if (empty($updates)) {
            return back()->with('error', 'Tidak ada perubahan yang dipilih.');
        }

        $affectedBefore = Question::query()->whereIn('id', $ids)->with('classrooms')->get();
        Question::query()->whereIn('id', $ids)->update($updates);

        ActivityLogger::log(
            action: ActivityAction::EDIT_BULK_SOAL,
            description: 'Edit bulk '.count($ids).' soal',
            properties: ['jumlah_soal' => count($ids), 'ids' => $ids->values()->all(), 'updates' => array_keys($updates)],
        );
        $affectedAfter = Question::query()->whereIn('id', $ids)->with('classrooms')->get();
        $pairs = $affectedAfter->flatMap(fn (Question $q) => $q->classrooms->map(fn ($c) => [$q->subject_id, $c->id]))->unique(fn ($p) => $p[0].':'.$p[1])->values()->all();
        // Jika subject_id ikut diubah, pairs sudah pakai nilai baru; cek semua kombinasi terdampak.
        $warning = $this->weightWarningForGenericPairs($pairs);
        // Jika tidak terdampak kombinasi (misal hanya is_active), cek juga before agar under/over terdeteksi.
        if ($warning === null && ! empty($affectedBefore)) {
            $beforePairs = $affectedBefore->flatMap(fn (Question $q) => $q->classrooms->map(fn ($c) => [$q->subject_id, $c->id]))->unique(fn ($p) => $p[0].':'.$p[1])->values()->all();
            $warning = $this->weightWarningForGenericPairs($beforePairs);
        }
        $redirect = back()->with('success', 'Pengaturan '.count($ids).' soal berhasil diperbarui.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Perbarui kelas target untuk sekelompok soal sekaligus (aksi Edit di Level 2).
     */
    public function bulkUpdateClassrooms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', Rule::exists('questions', 'id')],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer', Rule::exists('classes', 'id')],
        ]);

        $questions = Question::whereIn('id', $data['question_ids'])->get();
        foreach ($questions as $question) {
            $question->classrooms()->sync($data['classroom_ids']);
        }

        ActivityLogger::log(
            action: ActivityAction::EDIT_BULK_SOAL,
            description: 'Perbarui kelas target '.$questions->count().' soal',
            properties: ['jumlah_soal' => $questions->count(), 'question_ids' => $data['question_ids'], 'classroom_ids' => $data['classroom_ids']],
        );

        // Kumpulkan warning per kombinasi subject × classroom baru untuk pesan non-blocking di UI.
        $pairs = [];
        foreach ($questions as $q) {
            foreach ($data['classroom_ids'] as $cid) {
                $pairs[] = [(int) $q->subject_id, (int) $cid];
            }
        }
        $warning = $this->weightWarningForGenericPairs($pairs);
        if ($warning !== null) {
            session()->flash('warning', $warning);
        }

        return response()->json(['ok' => true, 'warning' => $warning]);
    }

    /**
     * Preview data untuk konfirmasi hapus grup soal (aksi Hapus di Level 2).
     */
    public function groupDeletePreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', Rule::exists('questions', 'id')],
        ]);

        $questionIds = $data['question_ids'];
        $questionsCount = count($questionIds);
        $answeredCount = ExamAnswer::query()
            ->whereIn('question_id', $questionIds)
            ->distinct('question_id')
            ->count('question_id');

        return response()->json([
            'questions_count' => $questionsCount,
            'answered_count' => $answeredCount,
        ]);
    }

    /**
     * Soal tidak memiliki relasi langsung ke jadwal ujian (exam_schedules
     * terhubung lewat subject_id), sehingga "sedang dipakai di jadwal aktif"
     * tidak bisa dicek per soal. Sebagai pengganti, soal yang sudah tercatat
     * jawabannya oleh peserta mana pun tidak boleh dihapus agar arsip nilai
     * ujian tidak rusak.
     */
    private function questionAlreadyAnswered(int $questionId): bool
    {
        return ExamAnswer::query()->where('question_id', $questionId)->exists();
    }

    private function weightWarningForPairs(int $subjectId, array $classroomIds): ?string
    {
        if ($classroomIds === []) {
            return null;
        }
        $service = new QuestionWeightService;
        $classroomMap = Classroom::query()->whereIn('id', $classroomIds)->pluck('name', 'id');
        $subjectName = Subject::query()->whereKey($subjectId)->value('name') ?? "Mapel #{$subjectId}";
        $msgs = [];
        foreach ($classroomIds as $cid) {
            $result = $service->check($subjectId, (int) $cid);
            if ($result['status'] === 'ok') {
                continue;
            }
            $kelas = $classroomMap->get($cid, "Kelas #{$cid}");
            $total = number_format($result['total'], 2, ',', '.');
            $delta = number_format($result['delta'], 2, ',', '.');
            $arah = $result['status'] === 'over' ? "kelebihan {$delta}" : "kekurangan {$delta}";
            $msgs[] = "{$kelas} × {$subjectName}: total {$total} (harus 100, {$arah})";
        }
        if ($msgs === []) {
            return null;
        }

        return 'Perhatian bobot: '.implode('; ', $msgs).'. Perbaiki bobot di Bank Soal agar jadwal tidak terblokir.';
    }

    private function weightWarningForGenericPairs(array $pairs): ?string
    {
        if ($pairs === []) {
            return null;
        }
        $service = new QuestionWeightService;
        $subjectIds = collect($pairs)->pluck(0)->unique()->values()->all();
        $classroomIds = collect($pairs)->pluck(1)->unique()->values()->all();
        $subjectMap = Subject::query()->whereIn('id', $subjectIds)->pluck('name', 'id');
        $classroomMap = Classroom::query()->whereIn('id', $classroomIds)->pluck('name', 'id');
        $unique = collect($pairs)->unique(fn ($p) => $p[0].':'.$p[1])->values()->all();
        $msgs = [];
        foreach ($unique as [$sid, $cid]) {
            $result = $service->check((int) $sid, (int) $cid);
            if ($result['status'] === 'ok') {
                continue;
            }
            $subjectName = $subjectMap->get($sid, "Mapel #{$sid}");
            $kelas = $classroomMap->get($cid, "Kelas #{$cid}");
            $total = number_format($result['total'], 2, ',', '.');
            $delta = number_format($result['delta'], 2, ',', '.');
            $arah = $result['status'] === 'over' ? "kelebihan {$delta}" : "kekurangan {$delta}";
            $msgs[] = "{$kelas} × {$subjectName}: total {$total} (harus 100, {$arah})";
        }
        if ($msgs === []) {
            return null;
        }

        return 'Perhatian bobot: '.implode('; ', array_slice($msgs, 0, 5)).(count($msgs) > 5 ? ' dan '.(count($msgs) - 5).' lainnya' : '').'. Perbaiki bobot di Bank Soal.';
    }

    private function deleteImageFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Hapus semua media milik satu soal: gambar utama (image_path) + seluruh
     * gambar per-opsi di options. Dipanggil saat soal dihapus.
     */
    private function deleteQuestionMedia(Question $question): void
    {
        $paths = $question->optionImages();

        if (filled($question->image_path)) {
            $paths[] = $question->image_path;
        }

        if ($paths !== []) {
            Storage::disk('public')->delete(array_values(array_unique($paths)));
        }
    }

    /**
     * Salin file gambar ke nama unik baru di folder yang sama.
     * Mengembalikan path baru, atau null bila file asli tidak ada/memakai disk tak dikenal.
     */
    private function duplicateImageFile(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $newPath = 'question-images/'.Str::random(40).($extension !== '' ? '.'.$extension : '');

        $disk->copy($path, $newPath);

        return $newPath;
    }

    /**
     * Hapus file gambar dari banyak soal (dipanggil sebelum delete massal).
     * Termasuk gambar per-opsi di options.
     *
     * @param  array<int, int>  $ids
     */
    private function deleteImageFiles(array $ids): void
    {
        Question::query()
            ->whereIn('id', $ids)
            ->get(['id', 'type', 'image_path', 'options'])
            ->each(function (Question $question) {
                $this->deleteQuestionMedia($question);
            });
    }
}
