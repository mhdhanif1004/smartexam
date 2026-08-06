<?php

namespace App\Models;

use Database\Factories\ViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Violation extends Model
{
    /** @use HasFactory<ViolationFactory> */
    use HasFactory;

    public const TYPE_TAB_SWITCH = 'berpindah_tab';

    public const TYPE_BLUR = 'kehilangan_fokus';

    public const TYPE_RESIZE = 'resize_jendela';

    public const TYPE_FULLSCREEN_EXIT = 'keluar_fullscreen';

    /**
     * Jenis pelanggaran yang dilaporkan otomatis oleh mesin deteksi peserta.
     */
    public const AUTO_TYPES = [
        self::TYPE_TAB_SWITCH => 'Berpindah Tab/Aplikasi Lain',
        self::TYPE_BLUR => 'Kehilangan Fokus Jendela',
        self::TYPE_RESIZE => 'Perubahan Ukuran Jendela',
        self::TYPE_FULLSCREEN_EXIT => 'Keluar Mode Fullscreen',
    ];

    public const TYPE_LABELS = [
        'membawa_handphone' => 'Membawa Handphone',
        'mencontek' => 'Mencontek',
        'bicara_dengan_teman' => 'Bicara dengan Teman',
        'membuka_buku' => 'Membuka Buku',
        'keluar_ruangan' => 'Keluar Ruangan',
        self::TYPE_TAB_SWITCH => 'Berpindah Tab/Aplikasi Lain',
        self::TYPE_BLUR => 'Kehilangan Fokus Jendela',
        self::TYPE_RESIZE => 'Perubahan Ukuran Jendela',
        self::TYPE_FULLSCREEN_EXIT => 'Keluar Mode Fullscreen',
    ];

    protected $fillable = [
        'exam_session_id',
        'violation_type',
        'occurred_at',
        'reported_by',
        'handled_by_supervisor',
        'handled_at',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'handled_by_supervisor' => 'boolean',
            'handled_at' => 'datetime',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function student(): HasOneThrough
    {
        return $this->hasOneThrough(
            Student::class,
            ExamSession::class,
            'id',
            'id',
            'exam_session_id',
            'student_id'
        );
    }

    public function examSchedule(): HasOneThrough
    {
        return $this->hasOneThrough(
            ExamSchedule::class,
            ExamSession::class,
            'id',
            'id',
            'exam_session_id',
            'exam_schedule_id'
        );
    }
}
