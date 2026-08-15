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
}