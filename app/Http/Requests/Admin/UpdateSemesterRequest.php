<?php

namespace App\Http\Requests\Admin;

use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Semester $semester */
        $semester = $this->route('semester');
        $academicYearId = $this->integer('academic_year_id');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'jenis' => [
                'required',
                'string',
                Rule::in(['ganjil', 'genap']),
                Rule::unique('semesters', 'jenis')
                    ->where('academic_year_id', $academicYearId)
                    ->ignore($semester->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'Tahun ajaran wajib dipilih.',
            'academic_year_id.exists' => 'Tahun ajaran tidak valid.',
            'jenis.required' => 'Jenis semester wajib dipilih.',
            'jenis.in' => 'Jenis semester hanya boleh Ganjil atau Genap.',
            'jenis.unique' => 'Semester dengan jenis tersebut sudah ada pada tahun ajaran ini.',
        ];
    }
}
