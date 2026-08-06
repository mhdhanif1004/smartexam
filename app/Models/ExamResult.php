<?php

namespace App\Models;

use Database\Factories\ExamResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    /** @use HasFactory<ExamResultFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'total_score',
        'is_passed',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }
}
