<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'join_code',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Guru yang membuat mata pelajaran ini pertama kali.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Guru-guru pengampu (co-teaching — bisa lebih dari satu).
     */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_teacher')->withTimestamps();
    }

    /**
     * Siswa yang terdaftar di mata pelajaran ini (via kode kelas atau assign manual).
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'subject_student')
            ->withPivot(['enrollment_type', 'enrolled_at'])
            ->withTimestamps();
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function pblProjects()
    {
        return $this->hasMany(PblProject::class);
    }

    public function lessonPlans()
    {
        return $this->hasMany(LessonPlan::class);
    }

    /**
     * Generate kode kelas unik (6 karakter alfanumerik kapital, mis. "K3F9QX").
     * Dipakai saat membuat mapel baru maupun saat regenerate kode.
     */
    public static function generateUniqueJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (self::where('join_code', $code)->exists());

        return $code;
    }
}
