<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesQuestionTypes;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
    use ValidatesQuestionTypes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $letters = Rule::in(Question::OPTION_LETTERS);

        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'creator_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'question_text' => ['required', 'string'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'integer', Rule::exists('classes', 'id')],
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
        return [
            ...$this->questionTypeRules(),
            $this->validateGuruAssignmentScope(),
        ];
    }

    /**
     * Ketika admin mengupload soal "atas nama guru" (creator_user_id diisi),
     * kelas target wajib berada dalam cakupan penugasan guru tersebut
     * (classroom_id pada teacher_subject_class_assignments yang sudah
     * di-restore), dan mapel soal wajib diampu guru tsb. Ini penjaga backend
     * yang tidak bisa dilewati hanya dengan manipulasi request.
     */
    private function validateGuruAssignmentScope(): \Closure
    {
        return function (Validator $validator) {
            $creatorUserId = (int) $this->input('creator_user_id');

            if ($creatorUserId === 0) {
                return;
            }

            $guru = User::query()->find($creatorUserId)?->guruMapel;

            if (! $guru instanceof GuruMapel) {
                $validator->errors()->add('creator_user_id', 'Guru yang dipilih tidak valid atau belum memiliki profil guru mapel.');

                return;
            }

            $subjectId = (int) $this->input('subject_id');

            if ($subjectId !== 0 && ! $guru->ampuSubjectIds()->contains($subjectId)) {
                $validator->errors()->add('subject_id', 'Guru tidak mengampu mata pelajaran yang dipilih.');

                return;
            }

            if ($subjectId === 0) {
                return;
            }

            $allowedClassroomIds = $guru->ampuClassroomIds($subjectId);

            if ($allowedClassroomIds->isEmpty()) {
                $validator->errors()->add('classroom_ids', 'Guru tidak memiliki penugasan kelas untuk mata pelajaran ini.');

                return;
            }

            $submittedClassroomIds = collect($this->input('classroom_ids', []))
                ->map(fn ($id) => (int) $id);

            foreach ($submittedClassroomIds as $classroomId) {
                if (! $allowedClassroomIds->contains($classroomId)) {
                    $validator->errors()->add('classroom_ids', 'Soal atas nama guru hanya dapat ditargetkan ke kelas yang menjadi penugasan guru tersebut.');

                    return;
                }
            }
        };
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
            'subject_id.exists' => 'Mata pelajaran yang dipilih tidak valid.',
            'classroom_ids.required' => 'Pilih minimal satu kelas target untuk soal ini.',
            'classroom_ids.min' => 'Soal wajib memiliki minimal satu kelas target.',
            'classroom_ids.*.exists' => 'Salah satu kelas target tidak valid.',
            'image.image' => 'File yang diunggah harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 3 MB.',
            'true_false_answer.in' => 'Kunci jawaban harus Benar atau Salah.',
            'single_answer.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
            'multiple_answer.*.in' => 'Kunci jawaban harus berupa salah satu huruf opsi (A-E).',
        ];
    }
}
