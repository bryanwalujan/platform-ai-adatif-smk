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
        'media_files',
    ];

    protected $casts = ['media_files' => 'array'];

    protected $hidden = ['media_files'];

    protected $appends = ['attachments', 'file_url'];

    public function getAttachmentsAttribute(): array
    {
        $files = array_map(fn ($file) => [
            'id' => $file['id'], 'name' => $file['name'], 'mime_type' => $file['mime_type'],
            'size' => $file['size'],
            'url' => route('material.attachment', ['material' => $this->id, 'attachment' => $file['id']]),
        ], $this->media_files ?? []);
        if ($this->file_path) {
            $files[] = [
                'id' => 'legacy', 'name' => $this->file_name, 'mime_type' => $this->file_type,
                'size' => null, 'url' => url('/api/files/'.$this->file_path),
            ];
        }

        return $files;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->attachments[0]['url'] ?? null;
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
