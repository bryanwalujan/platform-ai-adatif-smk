@extends('guru.layout')
@section('title', 'Topik — ' . $subject->name)
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.subjects.show', $subject->id) }}" class="btn btn-sm">&larr; Kembali ke Mata Pelajaran</a>
        <a href="{{ route('guru.subjects.content.topics.create', $subject->id) }}" class="btn btn-primary btn-sm">+ Buat Topik</a>
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr><th>Urutan</th><th>Judul</th><th>Materi</th><th>Kuis</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($topics as $t)
                    <tr>
                        <td>{{ $t->order }}</td>
                        <td>{{ $t->title }}</td>
                        <td>{{ $t->materials_count }}</td>
                        <td>{{ $t->quizzes_count }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.content.topics.show', $t->id) }}" class="btn btn-sm">Kelola</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada topik. Buat topik pertama untuk mulai menambah materi & kuis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection