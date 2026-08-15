<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
