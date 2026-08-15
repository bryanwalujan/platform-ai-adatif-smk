@extends('admin.layout')

@section('title', $subject->name)

@section('content')
    <div class="panel" style="margin-bottom: 20px;">
        <div class="panel-header">
            {{ $subject->name }}
            @if (!$subject->is_active) <span class="badge badge-rejected">nonaktif</span> @endif
        </div>
        <div style="padding: 18px;">
            <p style="margin-top:0; color: var(--text-muted);">{{ $subject->description ?: '(tanpa deskripsi)' }}</p>
            <p>Kode Kelas: <code class="join-code">{{ $subject->join_code }}</code></p>
            <p>Dibuat oleh: {{ $subject->createdBy?->name ?? '-' }}</p>
            <p>Jumlah Topik: {{ $subject->topics_count }}</p>

            @if ($subject->is_active)
                <form method="POST" action="{{ route('admin.subjects.deactivate', $subject->id) }}"
                      onsubmit="return confirm('Nonaktifkan mata pelajaran \'{{ $subject->name }}\'? Siswa tidak akan bisa join lagi lewat kode kelas.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Nonaktifkan Mata Pelajaran</button>
                </form>
            @endif
        </div>
    </div>

    <div class="panel" style="margin-bottom: 20px;">
        <div class="panel-header">Guru Pengampu ({{ $subject->teachers->count() }})</div>

        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($subject->teachers as $teacher)
                    <tr>
                        <td>{{ $teacher->name }}</td>
                        <td>{{ $teacher->email }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.subjects.teachers.remove', [$subject->id, $teacher->id]) }}"
                                  onsubmit="return confirm('Lepas {{ $teacher->name }} dari mata pelajaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Lepas</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada guru pengampu.</td></tr>
                @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.subjects.teachers.add', $subject->id) }}" style="padding: 14px 18px; border-top: 1px solid var(--border); display:flex; gap:8px;">
            @csrf
            <input type="email" name="email" placeholder="Email guru yang mau ditambahkan" required
                   style="flex:1; padding:6px 10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
            <button type="submit" class="btn btn-primary btn-sm">Tambah sebagai Pengampu</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header">Siswa Terdaftar ({{ $subject->students->count() }})</div>

        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Cara Gabung</th></tr>
            </thead>
            <tbody>
                @forelse ($subject->students as $student)
                    <tr>
                        <td>{{ $student->name }}</td>
                        <td>{{ $student->email }}</td>
                        <td>{{ $student->pivot->enrollment_type === 'self_joined' ? 'Kode kelas' : 'Di-assign' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada siswa terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
