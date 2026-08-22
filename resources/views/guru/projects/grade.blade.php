{{-- resources/views/guru/projects/grade.blade.php --}}
@extends('guru.layout')
@section('title', 'Nilai Proyek')
@section('content')
    <div class="panel" style="padding: 20px; max-width: 640px;">
        <h3 style="margin-top:0;">{{ $project->title }}</h3>
        <p style="color:var(--text-muted); font-size:13px;">
            Siswa: {{ $project->user->name }} &middot; Topik: {{ $project->topic->title ?? '-' }}
        </p>
        <p>{{ $project->description }}</p>

        @if($project->file_path)
            <p><a href="{{ url('/api/files/' . $project->file_path) }}" target="_blank">📎 Lihat file yang diunggah siswa</a></p>
        @endif

        <form method="POST" action="{{ route('guru.projects.grade', $project->id) }}">
            @csrf

            <div class="rubric-row">
                <label style="margin:0;">Kreativitas (0-100)</label>
                <input type="number" name="rubric_scores[kreativitas]" min="0" max="100" required>
            </div>
            <div class="rubric-row">
                <label style="margin:0;">Teknis (0-100)</label>
                <input type="number" name="rubric_scores[teknis]" min="0" max="100" required>
            </div>
            <div class="rubric-row">
                <label style="margin:0;">Konsep (0-100)</label>
                <input type="number" name="rubric_scores[konsep]" min="0" max="100" required>
            </div>
            <div class="rubric-row">
                <label style="margin:0;">Presentasi (0-100)</label>
                <input type="number" name="rubric_scores[presentasi]" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label>Feedback untuk siswa</label>
                <textarea name="feedback" rows="4" maxlength="2000" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Penilaian</button>
        </form>
    </div>
@endsection