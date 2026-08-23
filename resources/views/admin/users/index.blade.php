@extends('admin.layout')
@section('title', 'Semua User')
@section('content')
    <div class="panel">
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Cari nama/email..." value="{{ $filters['search'] ?? '' }}">
            <select name="role">
                <option value="">Semua role</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                <option value="guru" @selected(($filters['role'] ?? '') === 'guru')>Guru</option>
                <option value="siswa" @selected(($filters['role'] ?? '') === 'siswa')>Siswa</option>
            </select>
            <select name="status">
                <option value="">Semua status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Ditolak</option>
            </select>
            <button type="submit" class="btn btn-sm">Filter</button>
        </form>

        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Daftar Sejak</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge badge-{{ $u->role }}">{{ $u->role }}</span></td>
                        <td><span class="badge badge-{{ $u->status === 'active' ? 'active' : ($u->status === 'pending' ? 'pending' : 'rejected') }}">{{ $u->status }}</span></td>
                        <td>{{ $u->created_at->format('d/m/Y') }}</td>
                        <td class="actions">
                            @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.status.update', $u->id) }}" style="display:inline-flex; gap:4px;">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" onchange="this.form.submit()" style="padding:4px 8px; border:1px solid var(--border); border-radius:6px; font-size:12px;">
                                        <option value="active"   @selected($u->status === 'active')>Aktif</option>
                                        <option value="pending"  @selected($u->status === 'pending')>Pending</option>
                                        <option value="rejected" @selected($u->status === 'rejected')>Ditolak</option>
                                    </select>
                                    <noscript><button type="submit" class="btn btn-sm">Simpan</button></noscript>
                                </form>
                            @else
                                <span style="color:var(--text-muted); font-size:12px;">— akun Anda —</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Tidak ada user yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">
        {{ $users->links() }}
    </div>
@endsection