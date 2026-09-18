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
        'created_by_user_id',
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
     * Pengguna yang membuat soal (guru mapel). Null untuk soal lama/buatan
     * admin yang tidak mengikuti alur kepemilikan guru.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
     * Ambil representasi teks sebuah opsi. Opsi tanpa gambar disimpan
     * sebagai string murni; opsi bergambar disimpan sebagai
     * array{text: string, image: ?string}.
     */
    public function optionText(mixed $option): string
    {
        if (is_string($option)) {
            return $option;
        }

        return (string) ($option['text'] ?? '');
    }

    /**
     * Kumpulkan seluruh path gambar di dalam options (per-opsi). Mencakup
     * single/multiple (key huruf), matching (left+right), dan true_false
     * (key 'true'/'false'). Digunakan untuk cleanup orphan file dan
     * duplikasi fisik saat soal di-copy.
     *
     * @return list<string>
     */
    public function optionImages(): array
    {
        return self::optionImagesFromOptions($this->options, $this->type);
    }

    /**
     * Variant statis dari optionImages() untuk array options mentah (mis.
     * payload baru yang belum di-persist). Shape sama persis dengan model.
     *
     * @param  array<mixed>|null  $options
     * @return list<string>
     */
    public static function optionImagesFromOptions(?array $options, string $type): array
    {
        $images = [];

        $collect = function (mixed $option) use (&$images): void {
            if (is_array($option) && isset($option['image']) && filled($option['image'])) {
                $images[] = (string) $option['image'];
            }
        };

        if ($type === Question::TYPE_MATCHING) {
            foreach (($options ?? [])['left'] ?? [] as $option) {
                $collect($option);
            }
            foreach (($options ?? [])['right'] ?? [] as $option) {
                $collect($option);
            }
        } else {
            foreach (($options ?? []) as $option) {
                $collect($option);
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Pasangan menjodohkan dari kolom options, dalam bentuk baris-baris
     * berpasangan agar mudah dipakai ulang oleh form Edit.
     *
     * @return array<int, array{left: string, left_image: ?string, right: string, right_image: ?string}>
     */
    public function matchingPairs(): array
    {
        $left = collect($this->options['left'] ?? []);
        $right = collect($this->options['right'] ?? []);

        return collect(range(0, max($left->count(), $right->count()) - 1))
            ->map(fn (int $index) => [
                'left' => $this->optionText($left[$index] ?? ''),
                'left_image' => is_array($left[$index] ?? null) ? ($left[$index]['image'] ?? null) : null,
                'right' => $this->optionText($right[$index] ?? ''),
                'right_image' => is_array($right[$index] ?? null) ? ($right[$index]['image'] ?? null) : null,
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

    /**
     * Batasi soal "milik" seorang guru mapel (dibuat olehnya). Soal buatan
     * admin (created_by_user_id null) dan soal guru lain tidak termasuk.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by_user_id', $user->id);
    }

    /**
     * Alias scope untuk kode yang lebih eksplisit: soal yang dibuat guru.
     */
    public function scopeCreatedByGuru(Builder $query, User $user): Builder
    {
        return $query->ownedBy($user);
    }
}
