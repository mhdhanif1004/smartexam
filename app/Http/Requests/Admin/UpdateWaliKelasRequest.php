<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWaliKelasRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->wali_kelas->user_id)],
            'classroom_id' => ['required', 'exists:classes,id', Rule::unique('wali_kelas', 'classroom_id')->ignore($this->wali_kelas->id)],
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
