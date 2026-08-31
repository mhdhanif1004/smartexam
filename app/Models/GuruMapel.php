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

    /**
     * ID mata pelajaran yang diampu guru ini (dari pivot penugasan mapel).
     *
     * @return Collection<int, int>
     */
    public function ampuSubjectIds(): Collection
    {
        return $this->assignments()->pluck('subject_id')->unique()->values();
    }

    /**
     * ID kelas yang menjadi cakupan akses guru ini untuk mapel tertentu.
     *
     * SUMBER KEBENARAN BARU: bukan lagi dari kolom classroom_id di pivot
     * penugasan, melainkan diturunkan secara dinamis dari kelas-kelas yang
     * menjadi target (pivot question_classroom) dari soal yang DIBUAT guru
     * ini untuk mapel tersebut. Karena bersifat live (query tiap request),
     * menghapus seluruh soal guru untuk sebuah kelas otomatis menghapus
     * akses guru ke kelas itu pada request berikutnya.
     *
     * Bila $subjectId null, seluruh kelas lintas mapel yang diampu guru
     * dikembalikan.
     *
     * @return Collection<int, int>
     */
    public function ampuClassroomIds(?int $subjectId = null): Collection
    {
        $query = $this->questionsQuery();

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        return $query
            ->with('classrooms')
            ->get()
            ->flatMap(fn (Question $question) => $question->classrooms->pluck('id'))
            ->unique()
            ->values();
    }

    /**
     * Cek apakah guru ini mengampu mapel $subjectId dan/atau target kelas
     * $classroomId. Kepemilikan mapel berasal dari pivot penugasan; cakupan
     * kelas berasal dari soal yang dibuat guru (lihat ampuClassroomIds).
     */
    public function isAmpu(?int $subjectId = null, ?int $classroomId = null): bool
    {
        if ($subjectId !== null && ! $this->ampuSubjectIds()->contains($subjectId)) {
            return false;
        }

        if ($classroomId !== null) {
            if (! $this->ampuClassroomIds($subjectId)->contains($classroomId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Peta subject_id => daftar kelas (id => [id, name]) yang menjadi cakupan
     * akses guru, diturunkan dari soal buatan guru. Dipakai form create/edit
     * soal dan dashboard agar pilihan kelas mengikuti mapel terpilih.
     *
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    public function classScopeBySubject(): array
    {
        $rows = $this->questionsQuery()
            ->with(['classrooms' => fn ($q) => $q->orderBy('name')])
            ->get();

        $map = [];

        foreach ($rows as $question) {
            foreach ($question->classrooms as $classroom) {
                $map[(int) $question->subject_id][(int) $classroom->id] = [
                    'id' => (int) $classroom->id,
                    'name' => (string) $classroom->name,
                ];
            }
        }

        foreach ($map as $subjectId => $classrooms) {
            $map[$subjectId] = array_values($classrooms);
        }

        return $map;
    }

    /**
     * Kueri dasar soal yang "dimiliki" guru ini (dibuat oleh user-nya),
     * siap difilter lanjut per mapel.
     */
    private function questionsQuery()
    {
        return Question::query()->where('created_by_user_id', $this->user_id);
    }
}
