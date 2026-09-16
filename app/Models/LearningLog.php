<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LearningLog extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'user_id',
        'topic_id',
        'material_id',
        'quiz_score',
        'time_spent_minutes',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
