<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name'];

    /**
     * Ambil id kelas dari nama; buat otomatis bila belum ada di master data.
     * Dipakai semua jalur penulisan siswa agar class_name dan classroom_id
     * selalu sinkron.
     */
    public static function idForName(string $name): int
    {
        return self::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'classroom_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_classroom')->withTimestamps();
    }
}
