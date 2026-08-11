<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamPeriodGroupsRequest extends FormRequest
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
            'class_name' => ['required', 'string', 'max:100', Rule::in($classNames)],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*' => ['integer', Rule::exists('rooms', 'id')],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'subjects.*.start_time' => ['required', 'date_format:H:i'],
            'subjects.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
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

                    if (isset($row['start_time'], $row['duration_minutes'])) {
                        $start = Carbon::createFromFormat('H:i', (string) $row['start_time']);
                        $end = $start->copy()->addMinutes((int) $row['duration_minutes']);

                        if ($end->format('H:i') <= $start->format('H:i')) {
                            $validator->errors()->add(
                                "subjects.{$index}.start_time",
                                'Waktu selesai ujian melebihi pukul 24:00. Periksa kembali durasi.'
                            );
                        }
                    }
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
            'class_name' => 'kelas',
            'rooms' => 'ruangan',
            'subjects' => 'mata pelajaran',
            'subjects.*.subject_id' => 'mata pelajaran',
            'subjects.*.start_time' => 'jam mulai',
            'subjects.*.duration_minutes' => 'durasi',
        ];
    }
}
