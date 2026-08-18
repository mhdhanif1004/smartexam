<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportQuestionsRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Question::TYPES))],
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'integer', Rule::exists('classes', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Pilih jenis soal terlebih dahulu.',
            'type.in' => 'Jenis soal tidak valid.',
            'file.required' => 'Pilih file Excel/CSV terlebih dahulu.',
            'file.extensions' => 'Format file harus xlsx, xls, atau csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
            'classroom_ids.required' => 'Pilih minimal satu kelas target untuk soal yang diimpor.',
            'classroom_ids.min' => 'Soal yang diimpor wajib memiliki minimal satu kelas target.',
            'classroom_ids.*.exists' => 'Salah satu kelas target tidak valid.',
        ];
    }
}
