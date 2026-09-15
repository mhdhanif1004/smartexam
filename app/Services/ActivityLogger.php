<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Pencatat audit log terpusat — safety net redaction + never-throw.
 *
 * Dipakai di 18 titik MVP:
 *   ActivityLogger::log(action: 'hapus_siswa', causer: auth()->user(), subject: $student, description: "...", properties: [...]);
 *
 * Prinsip:
 * - Redaction otomatis untuk key sensitif sebelum simpan (safety net).
 * - Tangkap ip_address & user_agent dari request saat ada.
 * - Snapshot causer_role saat aksi terjadi (role bisa berubah nanti).
 * - JANGAN pernah membuat request utama gagal — bungkus try-catch, gagal log -> Log::error saja.
 */
class ActivityLogger
{
    /**
     * Key yang WAJIB di-redact — nilai mentah tidak boleh tersimpan di properties JSON.
     * Perbandingan case-insensitive; cek recursive pada array nested.
     */
    private const REDACTED_KEYS = [
        'password',
        'plain_password',
        'remember_token',
        'token_code',
        'token',
        'student_answer',
    ];

    private const REDACTED_PLACEHOLDER = '[REDACTED]';

    /**
     * Catat satu baris audit log.
     *
     * @param  string|ActivityAction  $action  Nilai enum ActivityAction atau string snake_case-nya.
     * @param  User|null  $causer  Pelaku; bila null akan fallback ke auth()->user().
     * @param  Model|object|null  $subject  Target (Model Eloquent) — diambil subject_type & subject_id-nya.
     * @param  string  $description  Ringkasan human-readable (Indonesia).
     * @param  array|null  $properties  Detail before/after/ringkasan — SUDAH di-redact otomatis.
     */
    public static function log(
        string|ActivityAction $action,
        ?User $causer = null,
        mixed $subject = null,
        string $description = '',
        ?array $properties = null,
    ): ?ActivityLog {
        try {
            // Fallback causer dari session bila tidak dioper eksplisit.
            if ($causer === null && function_exists('auth') && auth()->check()) {
                $causer = auth()->user();
            }

            $actionValue = $action instanceof ActivityAction ? $action->value : $action;

            // Snapshot role — penting karena role user bisa berubah di kemudian hari.
            $causerRole = $causer?->role ?? 'system';

            // Ekstrak subject_type & subject_id bila subject adalah Model.
            $subjectType = null;
            $subjectId = null;

            if ($subject instanceof Model) {
                $subjectType = $subject::class;
                $subjectId = $subject->getKey();
            } elseif (is_object($subject) && method_exists($subject, 'getKey') && method_exists($subject, 'getTable')) {
                $subjectType = $subject::class;
                $subjectId = $subject->getKey();
            }

            // Redaction otomatis sebelum simpan — safety net supaya pemanggil tidak perlu ingat manual.
            $cleanProperties = $properties !== null ? self::redact($properties) : null;

            // Tangkap konteks request bila ada (null saat dijalankan dari console/queue/test tanpa request).
            $ipAddress = null;
            $userAgent = null;

            if (function_exists('request') && request() !== null) {
                try {
                    $ipAddress = request()->ip();
                } catch (\Throwable) {
                    $ipAddress = null;
                }

                try {
                    $ua = request()->userAgent();
                    $userAgent = $ua !== null ? mb_substr($ua, 0, 1024) : null;
                } catch (\Throwable) {
                    $userAgent = null;
                }
            }

            return ActivityLog::create([
                'causer_id' => $causer?->getKey(),
                'causer_role' => $causerRole,
                'action' => $actionValue,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'description' => $description !== '' ? $description : $actionValue,
                'properties' => $cleanProperties,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            // JANGAN pernah melempar exception ke pemanggil — cukup catat ke laravel.log.
            Log::error('ActivityLogger gagal mencatat log', [
                'action' => $action instanceof ActivityAction ? $action->value : $action,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Redact key sensitif secara recursive (case-insensitive).
     * Key yang cocok diganti placeholder '[REDACTED]' agar audit tetap tahu field ada tanpa bocor nilai.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function redact(array $data): array
    {
        $redactedKeys = array_map('strtolower', self::REDACTED_KEYS);
        $result = [];

        foreach ($data as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : null;
            $isRedacted = $lowerKey !== null && in_array($lowerKey, $redactedKeys, true);

            if ($isRedacted) {
                $result[$key] = self::REDACTED_PLACEHOLDER;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::redact($value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Daftar key terlarang — dipakai test untuk verifikasi redaction.
     *
     * @return string[]
     */
    public static function redactedKeys(): array
    {
        return self::REDACTED_KEYS;
    }
}
