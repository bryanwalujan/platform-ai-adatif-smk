@extends('guru.layout')
@section('title', 'Edit Kuis')
@php($breadcrumbs = [
    $quiz->topic->subject->name => route('guru.subjects.show', $quiz->topic->subject_id),
    $quiz->topic->title => route('guru.content.topics.show', $quiz->topic_id),
    'Edit Kuis' => null,
])
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <form method="POST" action="{{ route('guru.content.quizzes.update', $quiz->id) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Judul Kuis</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title', $quiz->title) }}">
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <select name="type">
                    <option value="regular" @selected(old('type', $quiz->type) === 'regular')>Reguler</option>
                    <option value="pre_test" @selected(old('type', $quiz->type) === 'pre_test')>Pre-Test</option>
                    <option value="post_test" @selected(old('type', $quiz->type) === 'post_test')>Post-Test</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nilai Kelulusan (0-100)</label>
                <input type="number" name="passing_score" min="0" max="100" value="{{ old('passing_score', $quiz->passing_score) }}">
            </div>
            <div class="form-group">
                <label>Batas Waktu (menit)</label>
                <input type="number" name="time_limit_minutes" min="1" value="{{ old('time_limit_minutes', $quiz->time_limit_minutes) }}">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection