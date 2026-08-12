<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamPeriodAutoGenerateRequest extends FormRequest
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
        $classNames = $this->classes();

        return [
            'name' => ['required', 'string', 'max:100'],
            'exam_date' => ['required', 'date'],
            'class_names' => ['required', 'array', 'min:1'],
            'class_names.*' => ['required', 'string', 'max:100', Rule::in($classNames)],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*' => ['integer', Rule::exists('rooms', 'id')],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'subjects.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'start_time' => ['required', 'date_format:H:i'],
            'gap_minutes' => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rows = (array) $this->input('subjects', []);
                $seen = [];

                foreach ($rows as $index => $row) {
                    $subjectId = (int) ($row['subject_id'] ?? 0);

                    if (isset($seen[$subjectId])) {
                        $validator->errors()->add(
                            "subjects.{$index}.subject_id",
                            "Mata pelajaran ini sudah dipilih pada baris ke-{$seen[$subjectId]} dalam kelompok yang sama."
                        );
                    }
                    $seen[$subjectId] = $index + 1;
                }

                foreach ($rows as $index => $row) {
                    if (! isset($row['subject_id'])) {
                        continue;
                    }

                    $subject = Subject::query()->withCount(['questions as active_count' => fn ($query) => $query->where('is_active', true)])
                        ->find((int) $row['subject_id']);

                    if ($subject === null || (int) $subject->active_count === 0) {
                        $validator->errors()->add(
                            "subjects.{$index}.subject_id",
                            'Mata pelajaran ini belum memiliki soal aktif. Tambahkan soal terlebih dahulu di Bank Soal.'
                        );
                    }
                }
            },
        ];
    }

    /**
     * Daftar nama kelas yang sah, diambil dari kelas yang terdaftar pada siswa.
     *
     * @return array<int, string>
     */
    private function classes(): array
    {
        return Student::query()
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->all();
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama ujian',
            'exam_date' => 'tanggal',
            'class_names' => 'kelas',
            'class_names.*' => 'kelas',
            'rooms' => 'ruangan',
            'subjects' => 'mata pelajaran',
            'subjects.*.subject_id' => 'mata pelajaran',
            'subjects.*.duration_minutes' => 'durasi',
            'start_time' => 'jam mulai',
            'gap_minutes' => 'jeda antar sesi',
        ];
    }
}
