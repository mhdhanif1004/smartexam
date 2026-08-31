<?php

namespace App\Http\Requests\GuruMapel;

use App\Http\Requests\Admin\Concerns\ValidatesQuestionTypes;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGuruMapelQuestionRequest extends FormRequest
{
    use ValidatesQuestionTypes;

    public function authorize(): bool
    {
        $user = $this->user();
        $question = $this->route('question');
        $guru = $user->guruMapel;

        if ($guru === null || ! $question instanceof Question) {
            return false;
        }

        // Hanya pemilik soal yang boleh mengelola; dan mapel soal harus tetap
        // dalam ampu-an guru.
        if ($question->created_by_user_id !== $user->id) {
            return false;
        }

        $subjectId = $question->subject_id;

        if (! $guru->isAmpu(subjectId: $subjectId)) {
            return false;
        }

        $classroomIds = collect($this->input('classroom_ids', []))->map('intval');

        if ($classroomIds->isEmpty()) {
            return false;
        }

        // Guru boleh mempertahankan/menetapkan kelas target manapun untuk
        // mapel yang diampunya. Cakupan kelas untuk Nilai/Absensi diturunkan
        // dari soal yang dibuatnya.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $letters = Rule::in(Question::OPTION_LETTERS);

        return [
            'subject_id' => ['required', 'integer', Rule::in([(int) ($this->route('question')?->subject_id)])],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'question_text' => ['required', 'string'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'integer'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_image' => ['nullable', 'boolean'],
            'score_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'single_options' => ['array'],
            'single_options.*' => ['nullable', 'string'],
            'single_answer' => ['nullable', $letters],
            'multiple_options' => ['array'],
            'multiple_options.*' => ['nullable', 'string'],
            'multiple_answer' => ['array'],
            'multiple_answer.*' => ['required', $letters],
            'true_false_answer' => ['nullable', Rule::in(['1', '0'])],
            'matching_left' => ['array'],
            'matching_right' => ['array'],
            'matching_left.*' => ['nullable', 'string'],
            'matching_right.*' => ['nullable', 'string'],
            'essay_answer' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return $this->questionTypeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis soal wajib dipilih.',
            'type.in' => 'Jenis soal tidak valid.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'classroom_ids.required' => 'Pilih minimal satu kelas target untuk soal ini.',
            'classroom_ids.min' => 'Soal wajib memiliki minimal satu kelas target.',
            'image.image' => 'File yang diunggah harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 3 MB.',
            'true_false_answer.in' => 'Kunci jawaban harus Benar atau Salah.',
            'single_answer.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
            'multiple_answer.*.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
        ];
    }
}
