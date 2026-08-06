<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'options',
        'answer_key',
        'score_weight',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'answer_key' => 'array',
            'score_weight' => 'decimal:2',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examAnswers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
