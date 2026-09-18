<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class QuestionImageOptimizer
{
    /**
     * Panjang sisi terpanjang maksimal hasil optimasi, dalam pixel.
     */
    public const MAX_DIMENSION = 1600;

    /**
     * Kualitas encode WebP (0-100).
     */
    public const WEBP_QUALITY = 80;

    /**
     * Folder tujuan di disk public (konsisten dengan imageUrl()).
     */
    public const DESTINATION_FOLDER = 'question-images';

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Optimasi gambar soal: auto-orient EXIF, resize down (max 1600px sisi
     * terpanjang, tidak pernah upscale), encode WebP kualitas 80, simpan ke
     * disk public. Return path relatif untuk kolom image_path.
     *
     * Auto-orient: intervention/image v3.11.8 Config::$autoOrientation
     * default true dan driver GD memanggil ->orient() saat read() pada
     * FilePathImageDecoder/BinaryImageDecoder (diverifikasi dari source
     * vendor), jadi orientasi EXIF sudah dikoreksi sebelum resize.
     *
     * @throws ValidationException bila file tidak bisa dibaca/diproses
     */
    public function optimize(UploadedFile $file): string
    {
        try {
            $image = $this->manager->read($file->getRealPath());

            // Resize DOWN saja: fit dalam kotak 1600x1600, pertahankan aspect
            // ratio, tidak pernah membesarkan gambar yang lebih kecil.
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            $encoded = $image->toWebp(self::WEBP_QUALITY);

            $filename = self::DESTINATION_FOLDER.'/'.Str::random(40).'.webp';
            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (Throwable $e) {
            Log::error('Gagal mengoptimasi gambar soal', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw ValidationException::withMessages([
                'image' => 'Gambar tidak dapat diproses, coba file lain.',
            ]);
        }
    }
}
