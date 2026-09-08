<?php

namespace App\Models;

use Database\Factories\WaliKelasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaliKelas extends Model
{
    /** @use HasFactory<WaliKelasFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'classroom_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
