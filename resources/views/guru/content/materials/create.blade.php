@extends('guru.layout')
@section('title', 'Tambah Materi')
@php($breadcrumbs = [
    $topic->subject->name => route('guru.subjects.show', $topic->subject_id),
    $topic->title => route('guru.content.topics.show', $topic->id),
    'Tambah Materi' => null,
])
@section('content')
    <div class="panel" style="padding:20px; max-width:640px;">
        <p style="color:var(--text-muted); font-size:13px; margin-top:0;">Topik: {{ $topic->title }}</p>

        <form method="POST" action="{{ route('guru.content.materials.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="topic_id" value="{{ $topic->id }}">

            <div class="form-group">
                <label>Judul Materi</label>
                <input type="text" name="title" required value="{{ old('title') }}">
            </div>
            <div class="form-group">
                <label>Konten</label>
                <textarea name="content" rows="8" required>{{ old('content') }}</textarea>
            </div>
            <div class="form-group">
                <label>URL Video (opsional — YouTube/embed)</label>
                <input type="text" name="video_url" value="{{ old('video_url') }}">
            </div>
            <div class="form-group">
                <label>Durasi (menit, opsional)</label>
                <input type="number" name="duration_minutes" min="0" value="{{ old('duration_minutes') }}">
            </div>
            <div class="form-group">
                <label>Lampiran File (opsional — PDF, Word, PPT, gambar, maks 15MB)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Materi</button>
        </form>
    </div>
@endsection