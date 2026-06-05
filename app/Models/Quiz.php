<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'topic_id',
        'title',
        'time_limit_minutes',
        'passing_score',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }
}