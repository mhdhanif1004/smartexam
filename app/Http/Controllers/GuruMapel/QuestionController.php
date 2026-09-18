<?php

namespace App\Http\Controllers\GuruMapel;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuruMapel\StoreGuruMapelQuestionRequest;
use App\Http\Requests\GuruMapel\UpdateGuruMapelQuestionRequest;
use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\QuestionImageOptimizer;
use App\Services\QuestionWeightService;
use App\Traits\BuildsQuestionPayload;
use App\Traits\ScopesGuruMapel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuestionController extends Controller
{
    use BuildsQuestionPayload;
    use ScopesGuruMapel;

    public function __construct(
        private readonly QuestionImageOptimizer $imageOptimizer,
    ) {}

    public function index(Request $request): View
    {
        $guru = $this->currentGuru();

        $ampuSubjectIds = $guru->ampuSubjectIds();

        $questions = Question::query()
            ->with('subject', 'classrooms', 'creator')
            ->ownedBy($request->user())
            ->whereIn('subject_id', $ampuSubjectIds)
            ->when($request->filled('subject_id'), function ($query) use ($request, $ampuSubjectIds) {
                $subjectId = (int) $request->integer('subject_id');
                if ($ampuSubjectIds->contains($subjectId)) {
                    $query->where('subject_id', $subjectId);
                }
            })
            ->when($request->filled('search'), fn ($query) => $query->where('question_text', 'like', '%'.$request->string('search')->trim().'%'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $subjects = $this->ampuSubjects($guru);

        // Total bobot per (subject×classroom) untuk badge non-blocking di tabel guru.
        $weightService = new QuestionWeightService;
        $weightChecks = [];
        foreach ($ampuSubjectIds as $sid) {
            foreach ($weightService->totalsForSubject((int) $sid) as $cid => $total) {
                $weightChecks[(int) $sid][(int) $cid] = $weightService->check((int) $sid, (int) $cid);
            }
        }
        $classroomIdToName = Classroom::query()->pluck('name', 'id')->all();

        return view('guru_mapel.questions.index', compact('guru', 'questions', 'subjects', 'weightChecks', 'classroomIdToName'));
    }

    public function create(): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $examTypes = ExamType::query()->orderBy('sort_order')->get();
        $question = null;

        return view('guru_mapel.questions.create', compact('subjects', 'types', 'letters', 'examTypes', 'question'));
    }

    public function store(StoreGuruMapelQuestionRequest $request): RedirectResponse
    {
        $guru = $this->currentGuru();
        $data = $request->validated();

        $payload = $this->questionPayload($data);
        $payload['created_by_user_id'] = $request->user()->id;
        $payload['teacher_guru_mapel_id'] = $guru->id;
        if ($request->filled('exam_type_id')) {
            $payload['exam_type_id'] = (int) $request->input('exam_type_id');
        }

        if ($request->hasFile('image')) {
            $payload['image_path'] = $this->imageOptimizer->optimize($request->file('image'));
        }

        $question = Question::create($payload);

        // Kelas target di-snapshot dari cakupan kelas yang di-assign admin untuk
        // mapel ini (guru tidak lagi memilih manual saat create).
        $this->syncClassroomsFromAssignment($question, $guru);
        $question->load('classrooms');

        ActivityLogger::log(
            action: ActivityAction::TAMBAH_SOAL,
            subject: $question,
            description: 'Guru menambahkan soal baru: '.Str::limit((string) $question->question_text, 60),
            properties: ['question_id' => $question->id, 'subject_id' => $question->subject_id, 'type' => $question->type, 'classroom_ids' => $question->classrooms->pluck('id')->all()],
        );

        $warning = $this->weightWarningForPairs((int) $question->subject_id, $question->classrooms->pluck('id')->all());
        $redirect = redirect()->route('guru_mapel.questions.index')->with('success', 'Soal berhasil ditambahkan.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function edit(Question $question): View
    {
        $guru = $this->currentGuru();
        $this->authorizeQuestion($question, $guru);

        $subjects = $this->ampuSubjects($guru);
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $examTypes = ExamType::query()->orderBy('sort_order')->get();
        $question->load('classrooms');

        return view('guru_mapel.questions.edit', compact('question', 'subjects', 'types', 'letters', 'examTypes'));
    }

    public function update(UpdateGuruMapelQuestionRequest $request, Question $question): RedirectResponse
    {
        $guru = $this->currentGuru();
        $this->authorizeQuestion($question, $guru);
        $data = $request->validated();

        // Kunci mapel soal: subject_id tidak boleh diubah lewat edit. Dipaksa
        // memakai nilai eksisting soal sehingga manipulasi payload tidak bisa
        // memindahkan soal ke mapel lain.
        $data['subject_id'] = $question->subject_id;

        $oldOptionImages = $question->optionImages();

        $payload = $this->questionPayload($data);
        $payload['teacher_guru_mapel_id'] = $guru->id;
        $payload['exam_type_id'] = $request->filled('exam_type_id')
            ? (int) $request->input('exam_type_id')
            : null;

        if ($request->hasFile('image')) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = $this->imageOptimizer->optimize($request->file('image'));
        } elseif (! empty($data['remove_image'])) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = null;
        }

        $question->update($payload);

        // Cleanup orphan: hapus gambar opsi lama yang tidak lagi direferensikan
        // di options baru (termasuk saat type-change / opsi dihapus / gambar diganti).
        $newOptionImages = Question::optionImagesFromOptions($payload['options'], $payload['type']);
        $toDelete = array_values(array_diff($oldOptionImages, $newOptionImages));
        if ($toDelete !== []) {
            Storage::disk('public')->delete($toDelete);
        }

        // Saat edit, cakupan kelas direkalkulasi ulang dari assignment terbaru
        // guru untuk mapel soal ini (keputusan desain).
        $this->syncClassroomsFromAssignment($question, $guru);
        $question->load('classrooms');

        ActivityLogger::log(
            action: ActivityAction::UBAH_SOAL,
            subject: $question,
            description: 'Guru mengubah soal #'.$question->id.': '.Str::limit((string) $question->question_text, 60),
            properties: ['question_id' => $question->id, 'subject_id' => $question->subject_id, 'type' => $question->type, 'classroom_ids' => $question->classrooms->pluck('id')->all()],
        );

        $warning = $this->weightWarningForPairs((int) $question->subject_id, $question->classrooms->pluck('id')->all());
        $redirect = redirect()->route('guru_mapel.questions.index')->with('success', 'Soal berhasil diperbarui.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function destroy(Question $question): RedirectResponse
    {
        $guru = $this->currentGuru();
        $this->authorizeQuestion($question, $guru);

        if (ExamAnswer::query()->where('question_id', $question->id)->exists()) {
            return back()->with('error', 'Soal ini sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.');
        }

        $snapshotId = $question->id;
        $snapshotText = Str::limit((string) $question->question_text, 60);
        $snapshotSubjectId = $question->subject_id;
        $this->deleteQuestionMedia($question);
        $question->delete();

        ActivityLogger::log(
            action: ActivityAction::HAPUS_SOAL,
            description: "Guru menghapus soal #{$snapshotId}: {$snapshotText}",
            properties: ['question_id' => $snapshotId, 'subject_id' => $snapshotSubjectId, 'question_text' => $snapshotText],
        );

        return redirect()->route('guru_mapel.questions.index')
            ->with('success', 'Soal berhasil dihapus.');
    }

    /**
     * Hapus banyak soal sekaligus. Hanya soal milik guru ini dalam mapel yang
     * diampu yang diproses; soal yang sudah pernah dijawab peserta dilewati
     * (data integrity), dan file gambar ikut dihapus. Mengembalikan laporan
     * jumlah soal terhapus & yang dilewati.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_map('intval', $validated['ids']);

        $questions = Question::query()
            ->whereIn('id', $ids)
            ->ownedBy($request->user())
            ->whereIn('subject_id', $guru->ampuSubjectIds())
            ->get();

        if ($questions->isEmpty()) {
            return back()->with('error', 'Tidak ada soal valid yang dipilih untuk dihapus.');
        }

        // Soal yang sudah pernah dijawab tidak boleh dihapus.
        $answeredIds = ExamAnswer::query()
            ->whereIn('question_id', $questions->pluck('id'))
            ->distinct()
            ->pluck('question_id');

        $deletable = $questions->reject(fn ($question) => $answeredIds->contains($question->id));

        foreach ($deletable as $question) {
            $this->deleteQuestionMedia($question);
            $question->delete();
        }

        $deletedCount = $deletable->count();
        $skippedCount = $questions->count() - $deletedCount;

        if ($deletedCount === 0) {
            return back()->with('error', 'Tidak ada soal yang bisa dihapus (soal yang sudah pernah dijawab peserta tidak dapat dihapus).');
        }

        $message = $deletedCount.' soal berhasil dihapus.';
        if ($skippedCount > 0) {
            $message .= ' '.$skippedCount.' soal dilewati karena sudah pernah dijawab peserta.';
        }

        return redirect()->route('guru_mapel.questions.index')->with('success', $message);
    }

    /**
     * Aborsi 403 bila soal bukan milik guru ini atau mapelnya di luar ampu-an.
     */
    private function authorizeQuestion(Question $question, GuruMapel $guru): void
    {
        abort_unless(
            $question->created_by_user_id === auth()->id() && $guru->isAmpu(subjectId: $question->subject_id),
            403,
            'Anda tidak berhak mengakses soal ini.'
        );
    }

    /**
     * Sinkronkan relasi question_classroom dari cakupan kelas yang saat ini
     * di-assign guru untuk mapel soal. Sumber kebenaran tunggal target soal
     * adalah pivot penugasan (bukan input form, karena guru tidak lagi memilih
     * kelas target manual).
     */
    private function syncClassroomsFromAssignment(Question $question, GuruMapel $guru): void
    {
        $classroomIds = $guru->ampuClassroomIds($question->subject_id)->values()->all();

        $question->classrooms()->sync($classroomIds);
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

        return 'Perhatian bobot: '.implode('; ', $msgs).'. Perbaiki bobot agar jadwal tidak terblokir.';
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
}
