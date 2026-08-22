{{-- resources/views/guru/projects/pending.blade.php --}}
@extends('guru.layout')
@section('title', 'Proyek Belum Dinilai')
@section('content')
    <div class="panel">
        <table>
            <thead><tr><th>Judul</th><th>Siswa</th><th>Topik</th><th>Level</th><th>Dikirim</th><th></th></tr></thead>
            <tbody>
                @forelse($projects as $p)
                    <tr>
                        <td>{{ $p->title }}</td>
                        <td>{{ $p->user->name }}</td>
                        <td>{{ $p->topic->title ?? '-' }}</td>
                        <td>{{ $p->level }}</td>
                        <td>{{ $p->created_at->diffForHumans() }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.projects.grade.form', $p->id) }}" class="btn btn-primary btn-sm">Nilai</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Semua proyek sudah dinilai 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection