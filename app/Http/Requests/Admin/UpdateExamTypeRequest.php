<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('exam_types', 'code')->ignore($this->route('exam_type'))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'boleh_dijadwalkan_guru' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama jenis ujian wajib diisi.',
            'code.required' => 'Kode jenis ujian wajib diisi.',
            'code.unique' => 'Kode jenis ujian sudah ada.',
            'boleh_dijadwalkan_guru.boolean' => 'Nilai tidak valid.',
        ];
    }
}
