<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExamPeriodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'grade_level' => ['required', 'string', 'in:X,XI,XII'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $start = Carbon::createFromFormat('H:i', (string) $this->input('start_time'));
                $end = Carbon::createFromFormat('H:i', (string) $this->input('end_time'));

                if ($end->format('H:i') <= $start->format('H:i')) {
                    $validator->errors()->add('end_time', 'Jam selesai harus lebih lambat dari jam mulai.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama sesi',
            'grade_level' => 'tingkat',
            'exam_date' => 'tanggal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
        ];
    }
}
