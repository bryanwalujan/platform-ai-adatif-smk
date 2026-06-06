<?php
// app/Models/AppNotification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data'    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}