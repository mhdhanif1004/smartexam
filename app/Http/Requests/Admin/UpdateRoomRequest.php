<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($this->room->id)],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'supervisor_id' => ['nullable', 'integer', 'exists:supervisors,id'],
            'shift' => ['nullable', Rule::in(Student::SHIFTS)],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
        ];
    }
}
