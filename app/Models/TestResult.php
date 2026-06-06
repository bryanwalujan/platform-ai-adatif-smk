<?php
// app/Models/TestResult.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestResult extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'topic_id',
        'type',
        'score',
        'correct_answers',
        'total_questions',
        'time_spent_minutes',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function quiz()  { return $this->belongsTo(Quiz::class); }
    public function topic() { return $this->belongsTo(Topic::class); }
}