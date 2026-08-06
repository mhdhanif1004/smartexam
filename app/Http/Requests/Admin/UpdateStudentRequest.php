<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'nisn' => ['required', 'string', 'digits:10', Rule::unique('students', 'nisn')->ignore($this->student->id)],
            'class_name' => ['required', 'string', 'max:100', Rule::exists('classes', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
