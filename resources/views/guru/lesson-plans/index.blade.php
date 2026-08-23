@extends('guru.layout')
@section('title', 'RPP — ' . $subject->name)
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.subjects.show', $subject->id) }}" class="btn btn-sm">&larr; Kembali ke Mata Pelajaran</a>
        <a href="{{ route('guru.lesson-plans.create', $subject->id) }}" class="btn btn-primary btn-sm">+ Buat RPP</a>
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr><th>Pertemuan</th><th>Judul</th><th>Topik</th><th>Jadwal</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($plans as $p)
                    <tr>
                        <td>{{ $p->meeting_number }}</td>
                        <td>{{ $p->title }}</td>
                        <td>{{ $p->topic->title ?? '-' }}</td>
                        <td>{{ $p->scheduled_date ? \Carbon\Carbon::parse($p->scheduled_date)->format('d/m/Y') : '-' }}</td>
                        <td>
                            <span class="badge {{ $p->is_completed ? 'badge-active' : 'badge-pending' }}">
                                {{ $p->is_completed ? 'Selesai' : 'Belum' }}
                            </span>
                        </td>
                        <td class="actions">
                            <a href="{{ route('guru.lesson-plans.show', $p->id) }}" class="btn btn-sm">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada RPP untuk mata pelajaran ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection