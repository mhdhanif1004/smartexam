<?php

namespace App\Http\Requests\WaliKelas;

use App\Models\AttitudeAspect;
use App\Models\Student;
use App\Models\WaliKelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttitudeGradesBulkRequest extends FormRequest
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
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.aspect_id' => ['required', 'integer', 'exists:attitude_aspects,id'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Validasi silang: student_id harus benar-benar siswa di kelas wali yang login,
     * dan semua aspect_id harus valid untuk sistem (bukan aspek untuk kelas lain).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $wali = $this->user()->waliKelas;

            if ($wali === null) {
                return;
            }

            $studentId = (int) $this->input('student_id');

            // Cross-check: siswa harus milik kelas wali yang login
            if ($studentId > 0) {
                $studentExists = Student::query()
                    ->where('id', $studentId)
                    ->where('classroom_id', $wali->classroom_id)
                    ->exists();

                if (! $studentExists) {
                    $validator->errors()->add('student_id', 'Siswa tidak ditemukan di kelas Anda.');
                }
            }

            // Cross-check: semua aspect_id harus valid (cek di database)
            $grades = $this->input('grades', []);
            if (is_array($grades) && count($grades) > 0) {
                $aspectIds = collect($grades)->pluck('aspect_id')->unique()->filter();
                $validAspectIds = AttitudeAspect::query()
                    ->whereIn('id', $aspectIds)
                    ->pluck('id');

                foreach ($aspectIds as $aspectId) {
                    if (! $validAspectIds->contains($aspectId)) {
                        $validator->errors()->add('grades', "Aspek sikap dengan ID {$aspectId} tidak valid.");
                        break;
                    }
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
            'grades.required' => 'Data nilai sikap tidak boleh kosong.',
            'grades.array' => 'Data nilai sikap harus berupa array.',
            'grades.min' => 'Setidaknya satu aspek sikap harus diisi.',
            'grades.*.aspect_id.required' => 'Aspek sikap wajib dipilih.',
            'grades.*.aspect_id.exists' => 'Aspek sikap tidak valid.',
            'grades.*.score.numeric' => 'Nilai harus berupa angka.',
            'grades.*.score.min' => 'Nilai minimal adalah 0.',
            'grades.*.score.max' => 'Nilai maksimal adalah 100.',
            'grades.*.note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
