<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PENGAWAS = 'pengawas';

    public const ROLE_PESERTA = 'peserta';

    public const ROLE_GURU_MAPEL = 'guru_mapel';

    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';

    public const ROLE_WALI_KELAS = 'wali_kelas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'plain_password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plain_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function supervisor(): HasOne
    {
        return $this->hasOne(Supervisor::class);
    }

    public function guruMapel(): HasOne
    {
        return $this->hasOne(GuruMapel::class);
    }

    public function kepalaSekolah(): HasOne
    {
        return $this->hasOne(KepalaSekolah::class);
    }

    public function waliKelas(): HasOne
    {
        return $this->hasOne(WaliKelas::class);
    }

    public function reportedViolations(): HasMany
    {
        return $this->hasMany(Violation::class, 'reported_by');
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPengawas(): bool
    {
        return $this->role === self::ROLE_PENGAWAS;
    }

    public function isPeserta(): bool
    {
        return $this->role === self::ROLE_PESERTA;
    }

    public function isGuruMapel(): bool
    {
        return $this->role === self::ROLE_GURU_MAPEL;
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === self::ROLE_KEPALA_SEKOLAH;
    }

    public function isWaliKelas(): bool
    {
        return $this->role === self::ROLE_WALI_KELAS;
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_PENGAWAS => 'pengawas.dashboard',
            self::ROLE_GURU_MAPEL => 'guru_mapel.dashboard',
            self::ROLE_KEPALA_SEKOLAH => 'kepala_sekolah.dashboard',
            self::ROLE_WALI_KELAS => 'wali_kelas.dashboard',
            default => 'peserta.dashboard',
        };
    }
}
