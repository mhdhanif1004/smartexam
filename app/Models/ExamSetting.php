<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSetting extends Model
{
    protected $fillable = [
        'max_supervisors_per_room',
    ];

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public static function maxSupervisorsPerRoom(): int
    {
        $setting = static::current();

        return $setting ? (int) $setting->max_supervisors_per_room : (int) config('exam.max_supervisors_per_room', 3);
    }
}
