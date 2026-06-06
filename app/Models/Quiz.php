<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'topic_id',
        'title',
        'type',              // TAMBAH
        'time_limit_minutes',
        'passing_score',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    // Helper untuk cek tipe
    public function isPreTest(): bool  { return $this->type === 'pre_test'; }
    public function isPostTest(): bool { return $this->type === 'post_test'; }
    public function isRegular(): bool  { return $this->type === 'regular'; }
}