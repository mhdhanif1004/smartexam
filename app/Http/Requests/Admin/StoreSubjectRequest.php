<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
        ];
    }
}
