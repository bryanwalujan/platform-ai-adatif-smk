<?php
// app/Models/Discussion.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    protected $fillable = [
        'user_id', 'topic_id', 'title',
        'body', 'type', 'is_pinned',
        'is_resolved', 'replies_count',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'is_resolved'  => 'boolean',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function topic()   { return $this->belongsTo(Topic::class); }
    public function replies() { return $this->hasMany(DiscussionReply::class); }
}