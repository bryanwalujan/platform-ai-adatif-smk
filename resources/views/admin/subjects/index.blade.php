@extends('admin.layout')

@section('title', 'Mata Pelajaran')

@section('content')
    <div class="panel">
        <div class="panel-header">Semua Mata Pelajaran ({{ $subjects->count() }})</div>

        @if ($subjects->isEmpty())
            <div class="empty">Belum ada mata pelajaran.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kode Kelas</th>
                        <th>Guru</th>
                        <th>Siswa</th>
                        <th>Topik</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr>
                            <td>{{ $subject->name }}</td>
                            <td><code class="join-code">{{ $subject->join_code }}</code></td>
                            <td>{{ $subject->teachers_count }}</td>
                            <td>{{ $subject->students_count }}</td>
                            <td>{{ $subject->topics_count }}</td>
                            <td>
                                @if ($subject->is_active)
                                    <span class="badge badge-active">aktif</span>
                                @else
                                    <span class="badge badge-rejected">nonaktif</span>
                                @endif
                            </td>
                            <td class="actions">
                                <a href="{{ route('admin.subjects.show', $subject->id) }}" class="btn btn-sm">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
