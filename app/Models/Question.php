<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_MATCHING = 'matching';

    public const TYPE_ESSAY = 'essay';

    public const TYPES = [
        self::TYPE_SINGLE_CHOICE => 'Pilihan Ganda (1 jawaban)',
        self::TYPE_MULTIPLE_CHOICE => 'Pilihan Ganda (banyak jawaban)',
        self::TYPE_TRUE_FALSE => 'Benar / Salah',
        self::TYPE_MATCHING => 'Menjodohkan',
        self::TYPE_ESSAY => 'Essay',
    ];

    public const OPTION_LETTERS = ['A', 'B', 'C', 'D', 'E'];

    protected $fillable = [
        'subject_id',
        'type',
        'question_text',
        'image_path',
        'options',
        'answer_key',
        'score_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'answer_key' => 'array',
            'score_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Kelas-kelas yang berhak menerima soal ini. Sumber kebenaran tunggal
     * untuk targeting kelas: tanpa relasi ini soal tidak akan pernah muncul
     * pada ujian kelas manapun.
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'question_classroom')->withTimestamps();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucwords(str_replace('_', ' ', $this->type));
    }

    /**
     * URL publik gambar soal (di bawah public/storage), null jika tidak ada.
     *
     * Memakai asset('storage/...') agar host/port mengikuti request saat ini,
     * bukan APP_URL (yang bisa salah arah bila aplikasi diakses lewat port
     * lain — misalnya http://localhost:8000 vs APP_URL http://localhost).
     */
    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * Pasangan menjodohkan dari kolom options, dalam bentuk baris-baris
     * berpasangan agar mudah dipakai ulang oleh form Edit.
     *
     * @return array<int, array{left: string, right: string}>
     */
    public function matchingPairs(): array
    {
        $left = collect($this->options['left'] ?? []);
        $right = collect($this->options['right'] ?? []);

        return collect(range(0, max($left->count(), $right->count()) - 1))
            ->map(fn (int $index) => [
                'left' => (string) ($left[$index] ?? ''),
                'right' => (string) ($right[$index] ?? ''),
            ])
            ->filter(fn (array $pair) => $pair['left'] !== '' || $pair['right'] !== '')
            ->values()
            ->all();
    }

    public function examAnswers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    /**
     * Filter soal yang boleh dikerjakan siswa dari classroom_id tertentu.
     * Satu-satunya sumber kebenaran targeting kelas adalah pivot
     * question_classroom; soal tanpa relasi pivot tidak akan pernah muncul.
     */
    public function scopeTargetingClassroom(Builder $query, int $classroomId): Builder
    {
        return $query->whereHas('classrooms', fn (Builder $q) => $q->whereKey($classroomId));
    }
}
