<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectAttendance extends Model
{
    public const ATTENDANCE_TITLE = 'Kehadiran';

    protected $fillable = [
        'student_id',
        'classroom_id',
        'subject_id',
        'guru_mapel_id',
        'semester_id',
        'total_days',
        'present_days',
        'absent_days',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'total_days' => 'integer',
            'present_days' => 'integer',
            'absent_days' => 'integer',
        ];
    }

    /**
     * Skor kehadiran 0-100. Rumus disepakati: hadir / (hadir + tidak hadir) * 100.
     * present_days + absent_days = hari guru benar-benar melaksanakan KBM.
     */
    public function score(): ?float
    {
        $effectiveDays = $this->present_days + $this->absent_days;

        if ($effectiveDays <= 0) {
            return null;
        }

        return round($this->present_days / $effectiveDays * 100, 2);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Materialisasi skor kehadiran ke tabel subject_grades sebagai baris
     * dengan exam_type Kehadiran dan title konstan 'Kehadiran'. Identitas
     * baris di-identifikasi via (student, classroom, subject, semester)
     * + title, sehingga pemanggilan ulang bersifat updateOrCreate.
     */
    public function materialize(): ?SubjectGrade
    {
        $score = $this->score();

        if ($score === null) {
            return null;
        }

        $examTypeId = ExamType::query()->where('code', 'kehadiran')->value('id');

        if ($examTypeId === null) {
            return null;
        }

        return SubjectGrade::updateOrCreate(
            [
                'student_id' => $this->student_id,
                'classroom_id' => $this->classroom_id,
                'subject_id' => $this->subject_id,
                'guru_mapel_id' => $this->guru_mapel_id,
                'semester_id' => $this->semester_id,
                'exam_type_id' => $examTypeId,
                'title' => self::ATTENDANCE_TITLE,
            ],
            [
                'score' => $score,
                'source' => SubjectGrade::SOURCE_MANUAL,
                'is_override' => true,
                'taken_at' => now()->toDateString(),
            ]
        );
    }
}
