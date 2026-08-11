<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CardSetting extends Model
{
    protected $fillable = [
        'logo_kiri_path',
        'logo_kanan_path',
        'nama_sekolah',
        'nama_kepala_sekolah',
        'jabatan_kepala_sekolah',
        'tempat',
    ];

    public function scopeActive($query)
    {
        return $query->latest('id');
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public function logoKiriDataUri(): ?string
    {
        return $this->dataUri($this->logo_kiri_path);
    }

    public function logoKananDataUri(): ?string
    {
        return $this->dataUri($this->logo_kanan_path);
    }

    private function dataUri(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path);
        $data = base64_encode(Storage::disk('local')->get($path));

        return "data:{$mime};base64,{$data}";
    }
}
