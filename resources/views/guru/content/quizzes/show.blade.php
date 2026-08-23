@extends('guru.layout')
@section('title', $quiz->title)
@php($breadcrumbs = [
    $quiz->topic->subject->name => route('guru.subjects.show', $quiz->topic->subject_id),
    $quiz->topic->title => route('guru.content.topics.show', $quiz->topic_id),
    $quiz->title => null,
])
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.content.topics.show', $quiz->topic_id) }}" class="btn btn-sm">&larr; Kembali ke {{ $quiz->topic->title }}</a>
        <a href="{{ route('guru.content.quizzes.edit', $quiz->id) }}" class="btn btn-sm">Edit Kuis</a>
        <form method="POST" action="{{ route('guru.content.quizzes.destroy', $quiz->id) }}" style="display:inline-block"
              onsubmit="return confirm('Hapus kuis ini beserta seluruh soalnya?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Hapus Kuis</button>
        </form>
    </div>

    <p style="color:var(--text-muted); font-size:13px;">
        Tipe: {{ ['regular' => 'Reguler', 'pre_test' => 'Pre-Test', 'post_test' => 'Post-Test'][$quiz->type] ?? $quiz->type }}
        &middot; Nilai Lulus: {{ $quiz->passing_score }}
        &middot; Batas Waktu: {{ $quiz->time_limit_minutes }} menit
    </p>

    <div class="panel">
        <div class="panel-header">
            Soal Kuis ({{ $quiz->questions->count() }})
            <a href="{{ route('guru.content.questions.create', $quiz->id) }}" class="btn btn-sm btn-primary">+ Tambah Soal</a>
        </div>

        @forelse($quiz->questions as $i => $q)
            <div style="padding:16px 18px; border-bottom:1px solid var(--border);">
                <p style="margin:0 0 8px; font-weight:600;">{{ $i + 1 }}. {{ $q->question }}</p>
                <ul style="margin:0 0 8px; padding-left:20px; font-size:13px;">
                    @foreach((is_string($q->options) ? json_decode($q->options, true) : $q->options) as $opt)
                        <li style="{{ $opt === $q->correct_answer ? 'color: var(--success-text); font-weight:600;' : '' }}">
                            {{ $opt }} {{ $opt === $q->correct_answer ? '✓' : '' }}
                        </li>
                    @endforeach
                </ul>
                @if($q->explanation)
                    <p style="font-size:12px; color:var(--text-muted); margin:0 0 8px;">Penjelasan: {{ $q->explanation }}</p>
                @endif
                <a href="{{ route('guru.content.questions.edit', $q->id) }}" class="btn btn-sm">Edit</a>
                <form method="POST" action="{{ route('guru.content.questions.destroy', $q->id) }}" style="display:inline-block"
                      onsubmit="return confirm('Hapus soal ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                </form>
            </div>
        @empty
            <div class="empty">Belum ada soal di kuis ini.</div>
        @endforelse
    </div>
@endsection