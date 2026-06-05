<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTopicMastery extends Model
{
    protected $table = 'student_topic_mastery'; // ← explicit, tanpa 's'

    protected $fillable = [
        'user_id',
        'topic_id',
        'mastery_level',
        'attempts',
        'last_accessed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}