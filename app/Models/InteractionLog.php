<?php
// app/Models/InteractionLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionLog extends Model
{
    protected $fillable = [
        'user_id',
        'topic_id',
        'material_id',
        'action',
        'duration_seconds',
        'open_count',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function topic()    { return $this->belongsTo(Topic::class); }
    public function material() { return $this->belongsTo(Material::class); }
}