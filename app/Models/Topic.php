<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'title', 'description', 'order'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function studentMasteries()
    {
        return $this->hasMany(StudentTopicMastery::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }
    
    public function learningLogs()
    {
        return $this->hasMany(LearningLog::class);
    }
}