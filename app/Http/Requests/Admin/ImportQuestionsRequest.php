<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel/CSV terlebih dahulu.',
            'file.extensions' => 'Format file harus xlsx, xls, atau csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
