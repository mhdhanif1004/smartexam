<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
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

        // Saat filter aktif, preload grouped data per mapel. Tanpa filter,
        // data diambil via AJAX saat accordion dibuka (lazy-load).
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
                ])->render();
                $preloadedQuestionIds[$subject->id] = $questions->pluck('id')->values();
            }
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
        ));
    }

    /**
     * Endpoint AJAX untuk lazy-load soal per mata pelajaran saat accordion dibuka.
     */
    public function bySubject(Request $request, Subject $subject): JsonResponse
    {
        $questions = $this->questionsForSubject($request, $subject->id);
        $grouped = $this->groupQuestionsByClassroom($questions);

        $html = view('admin.questions.partials.question-groups', [
            'groups' => $grouped,
            'subject' => $subject,
            'search' => (string) $request->string('search')->trim(),
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

        return view('admin.questions.create', compact('subjects', 'types', 'letters', 'classrooms'));
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $payload = $this->payload($request->validated());

        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        }

        $question = Question::create($payload);
        $question->classrooms()->sync($request->validated()['classroom_ids']);

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Question $question): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $classrooms = Classroom::query()->orderBy('name')->get();

        return view('admin.questions.edit', compact('question', 'subjects', 'types', 'letters', 'classrooms'));
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();
        $payload = $this->payload($data);

        if ($request->hasFile('image')) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        } elseif (! empty($data['remove_image'])) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = null;
        }

        $question->update($payload);
        $question->classrooms()->sync($data['classroom_ids']);

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diperbarui.');
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

        $copy = Question::create([
            'subject_id' => $question->subject_id,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'answer_key' => $question->answer_key,
            'score_weight' => $question->score_weight,
            'is_active' => true,
        ]);

        $copy->classrooms()->sync($question->classrooms->pluck('id'));

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diduplikasi.');
    }

    public function toggleActive(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        $state = $question->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Soal berhasil {$state}.");
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
            'score_weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
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

        Question::query()->whereIn('id', $ids)->update($updates);

        return back()->with('success', 'Pengaturan '.count($ids).' soal berhasil diperbarui.');
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

        return response()->json(['ok' => true]);
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

    private function deleteImageFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
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

    /**
     * Bangun payload soal (options & answer_key) sesuai jenis soal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $options = null;
        $answerKey = null;

        switch ($data['type']) {
            case Question::TYPE_SINGLE_CHOICE:
                $options = $this->cleanOptions($data['single_options'] ?? []);
                $answerKey = $data['single_answer'];
                break;

            case Question::TYPE_MULTIPLE_CHOICE:
                $options = $this->cleanOptions($data['multiple_options'] ?? []);
                $answerKey = array_values(array_filter($data['multiple_answer'] ?? []));
                break;

            case Question::TYPE_TRUE_FALSE:
                $answerKey = (bool) ($data['true_false_answer'] ?? false);
                break;

            case Question::TYPE_MATCHING:
                [$left, $right] = $this->cleanPairs($data['matching_left'] ?? [], $data['matching_right'] ?? []);
                $options = ['left' => $left, 'right' => $right];
                $answerKey = collect(range(0, count($left) - 1))
                    ->mapWithKeys(fn (int $index) => [chr(65 + $index) => (string) ($index + 1)])
                    ->all();
                break;

            case Question::TYPE_ESSAY:
                $answerKey = $data['essay_answer'] ?? null;
                break;
        }

        return [
            'subject_id' => $data['subject_id'],
            'type' => $data['type'],
            'question_text' => $data['question_text'],
            'options' => $options,
            'answer_key' => $answerKey,
            'score_weight' => $data['score_weight'],
        ];
    }

    /**
     * @param  array<mixed>  $options
     * @return array<string, string>
     */
    private function cleanOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
            ->all();
    }

    /**
     * @param  array<mixed>  $leftInput
     * @param  array<mixed>  $rightInput
     * @return array{0: array<string>, 1: array<string>}
     */
    private function cleanPairs(array $leftInput, array $rightInput): array
    {
        $left = array_values($leftInput);
        $right = array_values($rightInput);

        $pairs = collect(range(0, max(count($left), count($right)) - 1))
            ->map(fn (int $index) => [trim((string) ($left[$index] ?? '')), trim((string) ($right[$index] ?? ''))])
            ->filter(fn (array $pair) => $pair[0] !== '' && $pair[1] !== '')
            ->values();

        return [
            $pairs->map(fn (array $pair) => $pair[0])->all(),
            $pairs->map(fn (array $pair) => $pair[1])->all(),
        ];
    }
}
