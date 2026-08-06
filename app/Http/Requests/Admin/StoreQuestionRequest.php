<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
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
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'question_text' => ['required', 'string'],
            'score_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'single_options' => ['required_if:type,single_choice', 'array'],
            'single_options.*' => ['nullable', 'string'],
            'single_answer' => ['required_if:type,single_choice', $letters],
            'multiple_options' => ['required_if:type,multiple_choice', 'array'],
            'multiple_options.*' => ['nullable', 'string'],
            'multiple_answer' => ['required_if:type,multiple_choice', 'array', 'min:1'],
            'multiple_answer.*' => ['required', $letters],
            'true_false_answer' => ['required_if:type,true_false', Rule::in(['1', '0'])],
            'matching_left' => ['required_if:type,matching', 'array'],
            'matching_right' => ['required_if:type,matching', 'array'],
            'matching_left.*' => ['nullable', 'string'],
            'matching_right.*' => ['nullable', 'string'],
            'essay_answer' => ['required_if:type,essay', 'string'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('type');

                if ($type === Question::TYPE_SINGLE_CHOICE || $type === Question::TYPE_MULTIPLE_CHOICE) {
                    $field = $type === Question::TYPE_SINGLE_CHOICE ? 'single_options' : 'multiple_options';
                    $filled = collect($this->input($field) ?? [])
                        ->filter(fn ($value) => filled($value))
                        ->count();

                    if ($filled < 2) {
                        $validator->errors()->add('options', 'Minimal 2 opsi jawaban harus diisi.');
                    }
                }

                if ($type === Question::TYPE_MATCHING) {
                    $left = collect($this->input('matching_left') ?? [])->filter(fn ($value) => filled($value));
                    $right = collect($this->input('matching_right') ?? [])->filter(fn ($value) => filled($value));

                    if ($left->isEmpty() || $left->count() !== $right->count()) {
                        $validator->errors()->add('matching_right', 'Jumlah pasangan kiri dan kanan harus sama dan minimal 1 pasangan.');
                    }
                }
            },
        ];
    }
}
