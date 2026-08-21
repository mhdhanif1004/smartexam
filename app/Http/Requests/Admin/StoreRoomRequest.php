<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
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
            'room_number' => ['required', 'integer', 'min:1', 'max:99999', Rule::unique('rooms', 'room_number')],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'supervisor_count' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
