@extends('guru.layout')
@section('title', 'Tambah Kuis')
@php($breadcrumbs = [
    $topic->subject->name => route('guru.subjects.show', $topic->subject_id),
    $topic->title => route('guru.content.topics.show', $topic->id),
    'Tambah Kuis' => null,
])
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <p style="color:var(--text-muted); font-size:13px; margin-top:0;">Topik: {{ $topic->title }}</p>

        <form method="POST" action="{{ route('guru.content.quizzes.store') }}">
            @csrf
            <input type="hidden" name="topic_id" value="{{ $topic->id }}">

            <div class="form-group">
                <label>Judul Kuis</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title') }}">
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <select name="type">
                    <option value="regular" @selected(old('type') === 'regular')>Reguler</option>
                    <option value="pre_test" @selected(old('type') === 'pre_test')>Pre-Test</option>
                    <option value="post_test" @selected(old('type') === 'post_test')>Post-Test</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nilai Kelulusan (0-100)</label>
                <input type="number" name="passing_score" min="0" max="100" value="{{ old('passing_score', 70) }}">
            </div>
            <div class="form-group">
                <label>Batas Waktu (menit)</label>
                <input type="number" name="time_limit_minutes" min="1" value="{{ old('time_limit_minutes', 30) }}">
            </div>
            <button type="submit" class="btn btn-primary">Buat Kuis</button>
        </form>
    </div>
@endsection