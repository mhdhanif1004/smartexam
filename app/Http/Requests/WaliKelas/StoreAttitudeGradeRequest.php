<?php

namespace App\Http\Requests\WaliKelas;

use App\Models\Student;
use App\Models\WaliKelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttitudeGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $wali = $this->user()->waliKelas;

        return $wali instanceof WaliKelas;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'attitude_aspect_id' => ['required', 'integer', 'exists:attitude_aspects,id'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Validasi silang: student_id harus benar-benar siswa di kelas wali yang login.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $wali = $this->user()->waliKelas;

            if ($wali === null) {
                return;
            }

            $studentId = (int) $this->input('student_id');

            if ($studentId > 0) {
                $studentExists = Student::query()
                    ->where('id', $studentId)
                    ->where('classroom_id', $wali->classroom_id)
                    ->exists();

                if (! $studentExists) {
                    $validator->errors()->add('student_id', 'Siswa tidak ditemukan di kelas Anda.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester wajib dipilih.',
            'semester_id.exists' => 'Semester tidak valid.',
            'student_id.required' => 'Siswa wajib dipilih.',
            'student_id.exists' => 'Siswa tidak ditemukan.',
            'attitude_aspect_id.required' => 'Aspek sikap wajib dipilih.',
            'attitude_aspect_id.exists' => 'Aspek sikap tidak valid.',
            'score.required' => 'Nilai wajib diisi.',
            'score.numeric' => 'Nilai harus berupa angka.',
            'score.min' => 'Nilai minimal adalah 0.',
            'score.max' => 'Nilai maksimal adalah 100.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
