@extends('guru.layout')
@section('title', $topic->title)
@php($breadcrumbs = [
    $topic->subject->name => route('guru.subjects.show', $topic->subject_id),
    'Topik' => route('guru.subjects.content.topics', $topic->subject_id),
    $topic->title => null,
])
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.content.topics', $topic->subject_id) }}" class="btn btn-sm">&larr; Kembali ke Topik</a>
        <a href="{{ route('guru.content.topics.edit', $topic->id) }}" class="btn btn-sm">Edit Topik</a>
        <form method="POST" action="{{ route('guru.content.topics.destroy', $topic->id) }}" style="display:inline-block"
              onsubmit="return confirm('Hapus topik ini beserta seluruh materi & kuis di dalamnya?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Hapus Topik</button>
        </form>
    </div>

    @if($topic->description)
        <p style="color:var(--text-muted);">{{ $topic->description }}</p>
    @endif

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">
            Materi
            <a href="{{ route('guru.content.materials.create', $topic->id) }}" class="btn btn-sm btn-primary">+ Tambah Materi</a>
        </div>
        <table>
            <thead><tr><th>Judul</th><th>Durasi</th><th>Video</th><th>File</th><th></th></tr></thead>
            <tbody>
                @forelse($materials as $m)
                    <tr>
                        <td>{{ $m->title }}</td>
                        <td>{{ $m->duration_minutes ? $m->duration_minutes . ' menit' : '-' }}</td>
                        <td>{{ $m->video_url ? 'Ada' : '-' }}</td>
                        <td>{{ $m->file_name ?? '-' }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.content.materials.edit', $m->id) }}" class="btn btn-sm">Edit</a>
                            <form method="POST" action="{{ route('guru.content.materials.destroy', $m->id) }}" style="display:inline-block"
                                  onsubmit="return confirm('Hapus materi ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada materi di topik ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-header">
            Kuis
            <a href="{{ route('guru.content.quizzes.create', $topic->id) }}" class="btn btn-sm btn-primary">+ Tambah Kuis</a>
        </div>
        <table>
            <thead><tr><th>Judul</th><th>Tipe</th><th>Nilai Lulus</th><th>Jumlah Soal</th><th></th></tr></thead>
            <tbody>
                @forelse($quizzes as $q)
                    <tr>
                        <td>{{ $q->title }}</td>
                        <td>{{ ['regular' => 'Reguler', 'pre_test' => 'Pre-Test', 'post_test' => 'Post-Test'][$q->type] ?? $q->type }}</td>
                        <td>{{ $q->passing_score }}</td>
                        <td>{{ $q->questions_count }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.content.quizzes.show', $q->id) }}" class="btn btn-sm">Kelola</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada kuis di topik ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection