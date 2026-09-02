<?php

namespace App\Http\Requests\GuruMapel;

use App\Http\Requests\Admin\Concerns\ValidatesQuestionTypes;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGuruMapelQuestionRequest extends FormRequest
{
    use ValidatesQuestionTypes;

    public function authorize(): bool
    {
        $guru = $this->user()->guruMapel;
        $subjectId = (int) $this->input('subject_id');

        if ($guru === null || $subjectId === 0 || ! $guru->isAmpu(subjectId: $subjectId)) {
            return false;
        }

        // Kelas target soal TIDAK lagi dipilih manual oleh guru. Saat create,
        // relasi question_classroom di-snapshot dari cakupan kelas yang di-assign
        // admin untuk mapel ini (lihat QuestionController::store).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $letters = Rule::in(Question::OPTION_LETTERS);

        return [
            'subject_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'question_text' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
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
            'image.image' => 'File yang diunggah harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 3 MB.',
            'true_false_answer.in' => 'Kunci jawaban harus Benar atau Salah.',
            'single_answer.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
            'multiple_answer.*.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
        ];
    }
}
