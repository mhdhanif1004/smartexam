<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'default_duration_minutes',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function guruMapels(): BelongsToMany
    {
        return $this->belongsToMany(
            GuruMapel::class,
            'teacher_subject_class_assignments',
            'subject_id',
            'guru_mapel_id'
        )->withTimestamps();
    }
}
