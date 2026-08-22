{{-- resources/views/guru/students/show.blade.php --}}
@extends('guru.layout')
@section('title', $student->name)
@section('content')
    <div class="cards">
        <div class="card"><div class="num">{{ $averageMastery }}%</div><div class="label">Rata-rata Mastery</div></div>
        <div class="card"><div class="num">{{ $pblLevel }}</div><div class="label">Level PBL</div></div>
    </div>

    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.students.notify.form', $student->id) }}" class="btn btn-primary btn-sm">Kirim Notifikasi</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">Mastery per Topik</div>
        <table>
            <thead><tr><th>Topik</th><th>Mastery</th><th>Percobaan</th><th>Terakhir Diakses</th></tr></thead>
            <tbody>
                @forelse($masteries as $m)
                    <tr>
                        <td>{{ $m['topic_title'] }}</td>
                        <td>{{ $m['mastery_level'] }}%</td>
                        <td>{{ $m['attempts'] }}</td>
                        <td>{{ $m['last_accessed'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada aktivitas belajar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-header">Proyek PBL</div>
        <table>
            <thead><tr><th>Judul</th><th>Level</th><th>Status</th><th>Nilai</th><th></th></tr></thead>
            <tbody>
                @forelse($projects as $p)
                    <tr>
                        <td>{{ $p->title }}</td>
                        <td>{{ $p->level }}</td>
                        <td><span class="badge badge-{{ $p->status }}">{{ $p->status }}</span></td>
                        <td>{{ $p->score ?? '-' }}</td>
                        <td class="actions">
                            @if($p->status === 'submitted')
                                <a href="{{ route('guru.projects.grade.form', $p->id) }}" class="btn btn-sm">Nilai</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada proyek.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection