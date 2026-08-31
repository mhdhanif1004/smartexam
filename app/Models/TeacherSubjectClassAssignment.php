<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubjectClassAssignment extends Model
{
    protected $fillable = [
        'guru_mapel_id',
        'subject_id',
        'classroom_id',
    ];

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
