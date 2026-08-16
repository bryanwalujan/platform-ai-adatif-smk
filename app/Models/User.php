<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',           // ← Ditambahkan
        'status',         // ← Ditambahkan: active | pending | rejected (approval akun guru)
        'email_verification_code',
        'email_verification_code_expires_at',
        // BARU: sempat lupa ditambahkan — akibatnya update(['email_verified_at' => ...])
        // di EmailVerificationController & MakeAdmin DIAM-DIAM diabaikan (mass
        // assignment protection Laravel menolak field yang tidak fillable
        // tanpa error), jadi status "sudah verifikasi" tidak pernah benar-benar
        // tersimpan. Ketahuan & diperbaiki lewat testing end-to-end.
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Kode OTP jangan pernah ikut ke-serialize ke response API mana pun —
        // meski formatUser() di AuthController sudah manual pilih field,
        // ini jaring pengaman kalau suatu saat ada endpoint lain yang
        // return model User apa adanya.
        'email_verification_code',
        'email_verification_code_expires_at',
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
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi dengan Proyek PBL
     */
    public function pblProjects()
    {
        return $this->hasMany(PblProject::class);
    }

    /**
     * Relasi dengan Mastery Siswa
     */
    public function studentMasteries()
    {
        return $this->hasMany(StudentTopicMastery::class);
    }

    /**
     * Mata pelajaran yang diampu user ini sebagai guru (co-teaching).
     */
    public function subjectsTeaching()
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher')->withTimestamps();
    }

    /**
     * Mata pelajaran yang diikuti user ini sebagai siswa.
     */
    public function subjectsEnrolled()
    {
        return $this->belongsToMany(Subject::class, 'subject_student')
            ->withPivot(['enrollment_type', 'enrolled_at'])
            ->withTimestamps();
    }

    /**
     * Cek apakah user adalah Guru
     */
    public function isTeacher(): bool
    {
        return $this->role === 'guru';
    }

    /**
     * Cek apakah user adalah Siswa
     */
    public function isStudent(): bool
    {
        return $this->role === 'siswa';
    }

    /**
     * Cek apakah user adalah Admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Cek apakah akun guru masih menunggu approval admin.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Cek apakah email sudah diverifikasi lewat kode OTP saat register.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Bentuk response user yang konsisten dipakai semua endpoint auth
     * (register/login/me/verify-email) — dulu di-duplikasi jadi
     * formatUser() privat di beberapa controller berbeda.
     */
    public function toAuthArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'role'      => $this->role,
            'is_guru'   => $this->role === 'guru',
            'is_admin'  => $this->role === 'admin',
            'status'    => $this->status,
            'email_verified' => $this->hasVerifiedEmail(),
            'photo_url' => $this->photo_path
                            ? \Illuminate\Support\Facades\Storage::url($this->photo_path)
                            : null,
        ];
    }
}