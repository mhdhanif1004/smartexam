<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuruMapel\StoreGuruMapelQuestionRequest;
use App\Http\Requests\GuruMapel\UpdateGuruMapelQuestionRequest;
use App\Models\ExamAnswer;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Traits\BuildsQuestionPayload;
use App\Traits\ScopesGuruMapel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class QuestionController extends Controller
{
    use BuildsQuestionPayload;
    use ScopesGuruMapel;

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

        return view('guru_mapel.questions.index', compact('guru', 'questions', 'subjects'));
    }

    public function create(): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $classroomsBySubject = $this->classroomsBySubject($guru);
        $question = null;

        return view('guru_mapel.questions.create', compact('subjects', 'types', 'letters', 'classroomsBySubject', 'question'));
    }

    public function store(StoreGuruMapelQuestionRequest $request): RedirectResponse
    {
        $guru = $this->currentGuru();
        $data = $request->validated();

        $payload = $this->questionPayload($data);
        $payload['created_by_user_id'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        }

        $question = Question::create($payload);
        $question->classrooms()->sync($data['classroom_ids']);

        return redirect()->route('guru_mapel.questions.index')
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Question $question): View
    {
        $guru = $this->currentGuru();
        $this->authorizeQuestion($question, $guru);

        $subjects = $this->ampuSubjects($guru);
        $types = Question::TYPES;
        $letters = Question::OPTION_LETTERS;
        $classroomsBySubject = $this->classroomsBySubject($guru);
        $question->load('classrooms');

        return view('guru_mapel.questions.edit', compact('question', 'subjects', 'types', 'letters', 'classroomsBySubject'));
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

        $payload = $this->questionPayload($data);

        if ($request->hasFile('image')) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = $request->file('image')->store('question-images', 'public');
        } elseif (! empty($data['remove_image'])) {
            $this->deleteImageFile($question->image_path);
            $payload['image_path'] = null;
        }

        $question->update($payload);
        $question->classrooms()->sync($data['classroom_ids']);

        return redirect()->route('guru_mapel.questions.index')
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $guru = $this->currentGuru();
        $this->authorizeQuestion($question, $guru);

        if (ExamAnswer::query()->where('question_id', $question->id)->exists()) {
            return back()->with('error', 'Soal ini sudah pernah dijawab oleh peserta pada ujian sebelumnya dan tidak bisa dihapus.');
        }

        $this->deleteImageFile($question->image_path);
        $question->delete();

        return redirect()->route('guru_mapel.questions.index')
            ->with('success', 'Soal berhasil dihapus.');
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
     * Peta subject_id => daftar kelas yang diampu guru untuk mapel itu.
     * Dipakai form create/edit agar pilihan kelas mengikuti mapel terpilih.
     *
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    private function classroomsBySubject(GuruMapel $guru): array
    {
        $assignments = $guru->assignments()->with('classroom')->get();

        $map = [];
        foreach ($assignments as $assignment) {
            $classroom = $assignment->classroom;
            if ($classroom === null) {
                continue;
            }
            $map[(int) $assignment->subject_id][(int) $classroom->id] = [
                'id' => (int) $classroom->id,
                'name' => (string) $classroom->name,
            ];
        }

        foreach ($map as $subjectId => $classrooms) {
            usort($classrooms, fn (array $a, array $b) => strcmp($a['name'], $b['name']));
            $map[$subjectId] = array_values($classrooms);
        }

        return $map;
    }

    private function deleteImageFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
