<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use BelongsToSchool;

    protected $fillable = ['topic_id',
        'title',
        'content',
        'video_url',
        'file_path',
        'file_name',
        'file_type',
        'duration_minutes',
        'order',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
