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

    public const TYPE_NATIVE_BACK = 'keluar_tombol_back';

    public const TYPE_EMERGENCY_EXIT = 'keluar_gesture_darurat';

    public const TYPE_UNPIN_SYSTEM = 'keluar_unpin_sistem';

    /**
     * Jenis pelanggaran yang dilaporkan otomatis oleh mesin deteksi peserta.
     * Meliputi deteksi browser (tab switch, blur, resize, fullscreen) dan
     * deteksi native dari Flutter WebView (back button, gesture, unpin).
     */
    public const AUTO_TYPES = [
        self::TYPE_TAB_SWITCH => 'Berpindah Tab/Aplikasi Lain',
        self::TYPE_BLUR => 'Kehilangan Fokus Jendela',
        self::TYPE_RESIZE => 'Perubahan Ukuran Jendela',
        self::TYPE_FULLSCREEN_EXIT => 'Keluar Mode Fullscreen',
        self::TYPE_NATIVE_BACK => 'Keluar via Tombol Back',
        self::TYPE_EMERGENCY_EXIT => 'Keluar via Gesture Darurat',
        self::TYPE_UNPIN_SYSTEM => 'Unpin Aplikasi dari Sistem',
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
        self::TYPE_NATIVE_BACK => 'Keluar via Tombol Back',
        self::TYPE_EMERGENCY_EXIT => 'Keluar via Gesture Darurat',
        self::TYPE_UNPIN_SYSTEM => 'Unpin Aplikasi dari Sistem',
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

    /**
     * Bentuk payload konsisten untuk panel notifikasi pelanggaran (dashboard
     * pengawas & admin) dan endpoint polling. Struktur tunggal ini menjaga
     * agar daftar yang ditampilkan di panel selalu sama bentuknya dengan data
     * awal (initialViolations) dari server.
     *
     * `$new` menandai apakah pelanggaran ini lewat `since` (id > lastSeenId).
     * `new = true` hanya dipakai client untuk memicu suara/notifikasi; SEMUA
     * item tetap dirender ke daftar panel.
     */
    public static function panelPayload(self $violation, bool $new = false): array
    {
        $schedule = $violation->examSession?->examSchedule;
        $student = $violation->examSession?->student;

        return [
            'id' => $violation->id,
            'session_id' => $violation->exam_session_id,
            'student_name' => $student?->user?->name ?? '-',
            'class_name' => $student?->class_name ?? '-',
            'subject' => $schedule?->subject?->name ?? '-',
            'room_name' => $schedule?->room?->display_name ?? '-',
            'violation_type' => $violation->violation_type,
            'violation_label' => self::typeLabel($violation->violation_type),
            'occurred_at' => $violation->occurred_at?->format('d M H:i'),
            'flags' => [
                (int) ($violation->examSession?->violation_flag_1 ?? 0),
                (int) ($violation->examSession?->violation_flag_2 ?? 0),
                (int) ($violation->examSession?->violation_flag_3 ?? 0),
            ],
            'flag_count' => $violation->examSession?->activeViolationFlags() ?? 0,
            'handled' => (bool) $violation->handled_by_supervisor,
            'new' => $new,
        ];
    }
}
