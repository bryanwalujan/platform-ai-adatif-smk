{{-- resources/views/guru/students/index.blade.php --}}
@extends('guru.layout')
@section('title', 'Siswa')
@section('content')
    <div class="panel">
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Cari nama/email..." value="{{ $search }}">
            @if($currentSubjectId)<input type="hidden" name="subject_id" value="{{ $currentSubjectId }}">@endif
            <button class="btn btn-sm" type="submit">Cari</button>
        </form>

        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Rata-rata Mastery</th><th>Topik Dipelajari</th><th>Proyek PBL</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($students as $s)
                    <tr>
                        <td>{{ $s['name'] }}</td>
                        <td>{{ $s['email'] }}</td>
                        <td>{{ $s['avg_mastery'] }}%</td>
                        <td>{{ $s['topics_learned'] }}</td>
                        <td>{{ $s['pbl_projects_count'] }}</td>
                        <td class="actions">
                            <a href="{{ route('guru.students.show', $s['id']) }}" class="btn btn-sm">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada siswa di mata pelajaran ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection