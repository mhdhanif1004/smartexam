<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_active',
        'boleh_dijadwalkan_guru',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'boleh_dijadwalkan_guru' => 'boolean',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamPeriod::class);
    }
}
