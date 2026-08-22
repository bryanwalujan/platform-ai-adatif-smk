{{-- resources/views/guru/projects/index.blade.php --}}
@extends('guru.layout')
@section('title', 'Semua Proyek')
@section('content')
    <div class="panel">
        <form method="GET" class="filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="submitted" @selected(($filters['status'] ?? '') === 'submitted')>Belum dinilai</option>
                <option value="graded" @selected(($filters['status'] ?? '') === 'graded')>Sudah dinilai</option>
            </select>
            @if($currentSubjectId)<input type="hidden" name="subject_id" value="{{ $currentSubjectId }}">@endif
        </form>

        <table>
            <thead><tr><th>Judul</th><th>Siswa</th><th>Topik</th><th>Status</th><th>Nilai</th><th></th></tr></thead>
            <tbody>
                @forelse($projects as $p)
                    <tr>
                        <td>{{ $p->title }}</td>
                        <td>{{ $p->user->name }}</td>
                        <td>{{ $p->topic->title ?? '-' }}</td>
                        <td><span class="badge badge-{{ $p->status }}">{{ $p->status }}</span></td>
                        <td>{{ $p->score ?? '-' }}</td>
                        <td class="actions">
                            @if($p->status === 'submitted')
                                <a href="{{ route('guru.projects.grade.form', $p->id) }}" class="btn btn-sm">Nilai</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada proyek.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection