<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RPP (Rencana Pelaksanaan Pembelajaran) — rencana pembelajaran per
 * pertemuan yang dibuat guru untuk mata pelajarannya sendiri. Cuma
 * kelihatan buat guru pengampu & siswa yang terdaftar di mapel itu
 * (di-scope lewat subject_id, sama seperti Topic/Material/dll).
 */
class LessonPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'created_by',
        'topic_id',
        'meeting_number',
        'title',
        'learning_objective',
        'description',
        'scheduled_date',
        'is_completed',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_completed'   => 'boolean',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
