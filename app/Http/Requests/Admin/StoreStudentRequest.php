<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
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
            'username' => ['nullable', 'string', 'alpha_num', 'min:10', 'max:15', Rule::unique('users', 'username')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'nisn' => ['required', 'string', 'digits:10', Rule::unique('students', 'nisn')],
            'class_name' => ['required', 'string', 'max:100', Rule::exists('classes', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
