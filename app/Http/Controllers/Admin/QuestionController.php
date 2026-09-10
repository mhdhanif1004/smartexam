<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
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
                $grouped = $this->groupQuestionsByClassroom($questions);
                $preloadedGroupHtml[$subject->id] = view('admin.questions.partials.question-groups', [
                    'groups' => $grouped,
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
        ));
    }

    /**
     * Endpoint AJAX untuk lazy-load soal per mata pelajaran saat accordion dibuka.
     */
    public function bySubject(Request $request, Subject $subject): JsonResponse
    {
        $questions = $this->questionsForSubject($request, $subject->id);
        $grouped = $this->groupQuestionsByClassroom($questions);

        $weightService = new QuestionWeightService;
        $weightChecks = [];
        foreach ($weightService->totalsForSubject($subject->id) as $cid => $total) {
            $weightChecks[(int) $cid] = $weightService->check($subject->id, (int) $cid);
        }
        $classroomIdToName = Classroom::query()->pluck('name', 'id')->all();

        $html = view('admin.questions.partials.question-groups', [
            'groups' => $grouped,
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
        $query = Question::query()->with('subject', 'classrooms')->where('subject_id', $subjectId);
        $this->applyContentFilters($request, $query);

        return $query->orderByDesc('id')->get();
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
        [$gurus, $guruClassroomsBySubject] = $this->guruScopeData();

        return view('admin.questions.create', compact(
            'subjects', 'types', 'letters', 'classrooms', 'gurus', 'guruClassroomsBySubject'
        ));
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $payload = $this->questionPayload($request->validated());

        if ($request->filled('creator_user_id')) {
            $payload['created_by_user_id'] = (int) $request->input('creator_user_id');
        }

        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        }

        $question = Question::create($payload);
        $classroomIds = $request->validated()['classroom_ids'];
        $question->classrooms()->sync($classroomIds);

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
        [$gurus, $guruClassroomsBySubject] = $this->guruScopeData();

        return view('admin.questions.edit', compact(
            'question', 'subjects', 'types', 'letters', 'classrooms', 'gurus', 'guruClassroomsBySubject'
        ));
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();
        $payload = $this->questionPayload($data);

        $payload['created_by_user_id'] = $request->filled('creator_user_id')
            ? (int) $request->input('creator_user_id')
            : null;

        if ($request->hasFile('image')) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        } elseif (! empty($data['remove_image'])) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = null;
        }

        $question->update($payload);
        $classroomIds = $data['classroom_ids'];
        $question->classrooms()->sync($classroomIds);

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

    public function destroy(Question $question): RedirectResponse
    {
        if ($this->questionAlreadyAnswered($question->id)) {
            return back()->with('error', 'Soal ini sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.');
        }

        $this->deleteImageFile($question->image_path);
        $question->delete();

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

        return back()->with('success', "{$deleted} soal berhasil dihapus.");
    }

    public function duplicate(Question $question): RedirectResponse
    {
        $question->load('classrooms');

        $payload = [
            'subject_id' => $question->subject_id,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'options' => $question->options,
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

        $warning = $this->weightWarningForPairs((int) $copy->subject_id, $copy->classrooms->pluck('id')->all());
        $redirect = redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diduplikasi.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function toggleActive(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

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
     *
     * @param  array<int, int>  $ids
     */
    private function deleteImageFiles(array $ids): void
    {
        $paths = Question::query()
            ->whereIn('id', $ids)
            ->whereNotNull('image_path')
            ->pluck('image_path')
            ->filter()
            ->all();

        if (! empty($paths)) {
            Storage::disk('public')->delete($paths);
        }
    }
}
