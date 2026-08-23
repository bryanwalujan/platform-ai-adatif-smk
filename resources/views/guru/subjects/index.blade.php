@extends('guru.layout')
@section('title', 'Mata Pelajaran Saya')
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.subjects.create') }}" class="btn btn-primary btn-sm">+ Buat Mata Pelajaran</a>
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr><th>Nama</th><th>Kode Kelas</th><th>Siswa</th><th>Topik</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($subjects as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td><code class="join-code">{{ $s->join_code }}</code></td>
                        <td>{{ $s->students_count }}</td>
                        <td>{{ $s->topics_count }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.subjects.show', $s->id) }}" class="btn btn-sm">Kelola</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Anda belum mengampu mata pelajaran apapun.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection