@extends('admin.layout')

@section('title', 'Semua User')

@section('content')
    <div class="panel">
        <div class="panel-header">Semua User ({{ $users->total() }})</div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="filters">
            <input type="text" name="search" placeholder="Cari nama/email..." value="{{ $filters['search'] ?? '' }}">
            <select name="role">
                <option value="">Semua Role</option>
                <option value="siswa" @selected(($filters['role'] ?? '') === 'siswa')>Siswa</option>
                <option value="guru" @selected(($filters['role'] ?? '') === 'guru')>Guru</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
            </select>
            <select name="status">
                <option value="">Semua Status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
            </select>
            <button type="submit" class="btn">Filter</button>
            @if (($filters['search'] ?? '') || ($filters['role'] ?? '') || ($filters['status'] ?? ''))
                <a href="{{ route('admin.users.index') }}" class="btn">Reset</a>
            @endif
        </form>

        @if ($users->isEmpty())
            <div class="empty">Tidak ada user yang cocok.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Daftar Pada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge badge-{{ $user->role }}">{{ $user->role }}</span></td>
                            <td><span class="badge badge-{{ $user->status }}">{{ $user->status }}</span></td>
                            <td>{{ $user->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 14px 18px;">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
