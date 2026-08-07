<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\ExamAnswer;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $questions = Question::query()
            ->with('subject')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('question_text', 'like', '%'.$request->string('search')->trim().'%');
            })
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), function ($query) use ($request) {
                $request->string('status') === 'aktif'
                    ? $query->where('is_active', true)
                    : $query->where('is_active', false);
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;

        return view('admin.questions.index', compact('questions', 'subjects', 'types'));
    }

    public function create(): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;

        return view('admin.questions.create', compact('subjects', 'types', 'letters'));
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        Question::create($this->payload($request->validated()));

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Question $question): View
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;

        return view('admin.questions.edit', compact('question', 'subjects', 'types', 'letters'));
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $question->update($this->payload($request->validated()));

        return redirect()->route('admin.questions.index')->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        if ($this->questionAlreadyAnswered($question->id)) {
            return back()->with('error', 'Soal ini sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.');
        }

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

        $deleted = Question::query()->whereIn('id', $ids)->delete();

        return back()->with('success', "{$deleted} soal berhasil dihapus.");
    }

    public function duplicate(Question $question): RedirectResponse
    {
        Question::create([
            'subject_id' => $question->subject_id,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'answer_key' => $question->answer_key,
            'score_weight' => $question->score_weight,
            'is_active' => true,
        ]);

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
