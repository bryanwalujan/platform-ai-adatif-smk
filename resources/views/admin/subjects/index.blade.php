@extends('admin.layout')
@section('title', 'Mata Pelajaran')
@section('content')
    <div class="panel">
        <table>
            <thead>
                <tr><th>Nama</th><th>Kode Kelas</th><th>Guru</th><th>Siswa</th><th>Topik</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($subjects as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td><code class="join-code">{{ $s->join_code }}</code></td>
                        <td>{{ $s->teachers_count }}</td>
                        <td>{{ $s->students_count }}</td>
                        <td>{{ $s->topics_count }}</td>
                        <td>
                            <span class="badge {{ $s->is_active ? 'badge-active' : 'badge-rejected' }}">
                                {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.subjects.show', $s->id) }}" class="btn btn-sm">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Belum ada mata pelajaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection