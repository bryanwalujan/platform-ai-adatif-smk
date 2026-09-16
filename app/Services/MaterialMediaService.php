<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaterialMediaService
{
    public const EXTENSIONS = 'jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,wav,ogg,m4a,pdf,txt,doc,docx,ppt,pptx,zip';

    public function save(Request $request, ?Material $material, int $topicId): Material
    {
        $fileRule = 'file|max:51200|mimes:'.self::EXTENSIONS.'|extensions:'.self::EXTENSIONS;
        $data = $request->validate([
            'title' => ($material ? 'sometimes' : 'required').'|string|max:255',
            'content' => 'nullable|string|max:60000',
            'video_url' => 'nullable|url:http,https|max:255',
            'duration_minutes' => 'sometimes|integer|min:0|max:1440',
            'files' => 'nullable|array|max:5',
            'files.*' => 'required|'.$fileRule,
            'file' => 'nullable|'.$fileRule,
            'remove_attachment_ids' => 'nullable|array|max:5',
            'remove_attachment_ids.*' => 'required|string|distinct',
        ]);
        $uploads = $request->file('files', []);
        if ($request->hasFile('file')) {
            $uploads[] = $request->file('file');
        }
        $stored = [];
        $removed = [];
        try {
            $result = DB::transaction(function () use ($material, $topicId, $data, $uploads, &$stored, &$removed) {
                $record = $material ? Material::lockForUpdate()->findOrFail($material->id) : new Material(['topic_id' => $topicId]);
                $files = $record->media_files ?? [];
                if ($record->file_path) {
                    $files[] = ['id' => 'legacy', 'path' => $record->file_path, 'name' => $record->file_name,
                        'mime_type' => $record->file_type, 'size' => Storage::disk('public')->exists($record->file_path) ? Storage::disk('public')->size($record->file_path) : 0];
                }
                $removeIds = $data['remove_attachment_ids'] ?? [];
                if (array_diff($removeIds, array_column($files, 'id'))) {
                    throw ValidationException::withMessages(['remove_attachment_ids' => 'Lampiran sudah berubah. Muat ulang materi.']);
                }
                $kept = [];
                foreach ($files as $file) {
                    if (in_array($file['id'], $removeIds, true)) {
                        $removed[] = $file['path'];
                    } else {
                        $kept[] = $file;
                    }
                }
                $size = array_sum(array_column($kept, 'size')) + array_sum(array_map(fn ($file) => $file->getSize(), $uploads));
                if (count($kept) + count($uploads) > 5 || $size > 100 * 1024 * 1024) {
                    throw ValidationException::withMessages(['files' => 'Maksimal 5 lampiran, 50 MB per file dan total 100 MB.']);
                }
                $content = array_key_exists('content', $data) ? ($data['content'] ?? '') : ($record->content ?? '');
                $video = array_key_exists('video_url', $data) ? $data['video_url'] : $record->video_url;
                if (! trim($content) && ! $video && ! $kept && ! $uploads) {
                    throw ValidationException::withMessages(['content' => 'Tambahkan penjelasan, lampiran, atau tautan video.']);
                }
                foreach ($uploads as $file) {
                    $path = $file->store('material_media', 'public');
                    if (! $path) {
                        throw new \RuntimeException('Lampiran gagal disimpan.');
                    }
                    $stored[] = $path;
                    $kept[] = ['id' => (string) Str::uuid(), 'path' => $path,
                        'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                        'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
                }
                $record->fill(collect($data)->only(['title', 'duration_minutes'])->all());
                $record->content = $content;
                $record->video_url = $video;
                $record->media_files = array_values($kept);
                $record->file_path = $record->file_name = $record->file_type = null;
                $record->save();

                return $record;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }
        Storage::disk('public')->delete($removed);

        return $result;
    }

    public function delete(Material $material): void
    {
        $paths = array_column($material->media_files ?? [], 'path');
        if ($material->file_path) {
            $paths[] = $material->file_path;
        }
        $material->delete();
        Storage::disk('public')->delete($paths);
    }
}
