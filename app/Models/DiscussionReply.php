<?php
// app/Models/DiscussionReply.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionReply extends Model
{
    protected $fillable = [
        'discussion_id', 'user_id',
        'body', 'is_best_answer',
    ];

    protected $casts = [
        'is_best_answer' => 'boolean',
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function discussion() { return $this->belongsTo(Discussion::class); }
}