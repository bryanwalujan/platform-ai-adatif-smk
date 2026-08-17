<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log SETIAP percobaan kuis (regular, pre_test, maupun post_test) — beda
 * dari TestResult yang cuma menyimpan satu baris pre_test dan satu baris
 * post_test per topik. Sumber data utama untuk
 * BayesianKnowledgeTracingService melacak urutan benar/salah siswa dari
 * waktu ke waktu.
 */
class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'topic_id',
        'quiz_type',
        'score',
        'passed',
    ];

    protected $casts = [
        'score' => 'float',
        'passed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
