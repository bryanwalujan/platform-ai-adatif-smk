<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class StudentTopicMastery extends Model
{
    use BelongsToSchool;

    protected $table = 'student_topic_mastery';

    protected $fillable = [
        'user_id',
        'topic_id',
        'mastery_level',
        'attempts',
        'last_accessed',
    ];

    // TAMBAH: cast last_accessed ke Carbon agar bisa pakai diffForHumans()
    protected $casts = [
        'last_accessed' => 'datetime',
        'mastery_level' => 'float',
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
