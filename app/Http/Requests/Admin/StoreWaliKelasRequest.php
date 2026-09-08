<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaliKelasRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'classroom_id' => ['required', 'exists:classes,id', Rule::unique('wali_kelas', 'classroom_id')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classroom_id.required' => 'Pilih kelas yang diampu wali kelas ini.',
            'classroom_id.unique' => 'Kelas tersebut sudah memiliki wali kelas lain.',
        ];
    }
}
