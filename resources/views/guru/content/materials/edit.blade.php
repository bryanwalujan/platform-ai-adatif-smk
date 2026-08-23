@extends('guru.layout')
@section('title', 'Edit Materi')
@php($breadcrumbs = [
    $material->topic->subject->name => route('guru.subjects.show', $material->topic->subject_id),
    $material->topic->title => route('guru.content.topics.show', $material->topic_id),
    'Edit Materi' => null,
])
@section('content')
    <div class="panel" style="padding:20px; max-width:640px;">
        <p style="color:var(--text-muted); font-size:13px; margin-top:0;">Topik: {{ $material->topic->title }}</p>

        <form method="POST" action="{{ route('guru.content.materials.update', $material->id) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Judul Materi</label>
                <input type="text" name="title" required value="{{ old('title', $material->title) }}">
            </div>
            <div class="form-group">
                <label>Konten</label>
                <textarea name="content" rows="8" required>{{ old('content', $material->content) }}</textarea>
            </div>
            <div class="form-group">
                <label>URL Video (opsional)</label>
                <input type="text" name="video_url" value="{{ old('video_url', $material->video_url) }}">
            </div>
            <div class="form-group">
                <label>Durasi (menit, opsional)</label>
                <input type="number" name="duration_minutes" min="0" value="{{ old('duration_minutes', $material->duration_minutes) }}">
            </div>
            @if($material->file_name)
                <p style="font-size:13px;">File saat ini: {{ $material->file_name }} <span style="color:var(--text-muted);">(ganti file belum didukung di form ini — hapus & buat ulang materi jika perlu ganti lampiran)</span></p>
            @endif
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection