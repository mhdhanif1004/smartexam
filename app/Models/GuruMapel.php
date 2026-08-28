<?php

namespace App\Models;

use Database\Factories\GuruMapelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GuruMapel extends Model
{
    /** @use HasFactory<GuruMapelFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nip',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectClassAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'teacher_subject_class_assignments',
            'guru_mapel_id',
            'subject_id'
        )->withTimestamps();
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(
            Classroom::class,
            'teacher_subject_class_assignments',
            'guru_mapel_id',
            'classroom_id'
        )->withTimestamps();
    }

    /**
     * ID mata pelajaran yang diampu guru ini (dari pivot penugasan).
     *
     * @return Collection<int, int>
     */
    public function ampuSubjectIds(): Collection
    {
        return $this->assignments()->pluck('subject_id')->unique()->values();
    }

    /**
     * ID kelas yang diampu guru ini. Bila $subjectId diberikan, hanya kelas
     * pada kombinasi penugasan mapel tersebut yang dikembalikan.
     *
     * @return Collection<int, int>
     */
    public function ampuClassroomIds(?int $subjectId = null): Collection
    {
        $query = $this->assignments();

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        return $query->pluck('classroom_id')->unique()->values();
    }

    /**
     * Cek apakah guru ini mengampu kombinasi (mapel, kelas). Saat $classroomId
     * null, hanya mengecek kepemilikan mapel; saat $subjectId null, hanya
     * mengecek kelas. Keduanya diisi = kombinasi penugasan harus persis cocok.
     */
    public function isAmpu(?int $subjectId = null, ?int $classroomId = null): bool
    {
        $query = $this->assignments();

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        if ($classroomId !== null) {
            $query->where('classroom_id', $classroomId);
        }

        return $query->exists();
    }
}
