@extends('admin.layout')
@section('title', $subject->name)
@section('content')
    <div class="cards">
        <div class="card"><div class="num">{{ $subject->students_count }}</div><div class="label">Siswa</div></div>
        <div class="card"><div class="num">{{ $subject->topics_count }}</div><div class="label">Topik</div></div>
        <div class="card"><div class="num"><code class="join-code">{{ $subject->join_code }}</code></div><div class="label">Kode Kelas</div></div>
    </div>

    <p style="color:var(--text-muted); font-size:13px;">
        Dibuat oleh {{ $subject->createdBy?->name ?? '-' }} &middot;
        Status:
        <span class="badge {{ $subject->is_active ? 'badge-active' : 'badge-rejected' }}">
            {{ $subject->is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
    </p>

    @if($subject->description)
        <p>{{ $subject->description }}</p>
    @endif

    <div class="panel" style="margin-bottom:20px; margin-top:20px;">
        <div class="panel-header">
            Guru Pengampu
            <form method="POST" action="{{ route('admin.subjects.teachers.add', $subject->id) }}" style="display:flex; gap:6px;">
                @csrf
                <input type="email" name="email" placeholder="Email guru..." required style="padding:6px 10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                <button type="submit" class="btn btn-sm btn-primary">Tambah</button>
            </form>
        </div>
        <table>
            <thead><tr><th>Nama</th><th>Email</th><th></th></tr></thead>
            <tbody>
                @forelse($subject->teachers as $t)
                    <tr>
                        <td>{{ $t->name }}</td>
                        <td>{{ $t->email }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.subjects.teachers.remove', [$subject->id, $t->id]) }}"
                                  onsubmit="return confirm('Lepas {{ $t->name }} dari mata pelajaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Lepas</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada guru pengampu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">Siswa Terdaftar</div>
        <table>
            <thead><tr><th>Nama</th><th>Email</th></tr></thead>
            <tbody>
                @forelse($subject->students as $s)
                    <tr><td>{{ $s->name }}</td><td>{{ $s->email }}</td></tr>
                @empty
                    <tr><td colspan="2" class="empty">Belum ada siswa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subject->is_active)
        <form method="POST" action="{{ route('admin.subjects.deactivate', $subject->id) }}"
              onsubmit="return confirm('Nonaktifkan mata pelajaran ini? Data topik/siswa yang sudah ada tidak akan terhapus.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Nonaktifkan Mata Pelajaran</button>
        </form>
    @endif
@endsection