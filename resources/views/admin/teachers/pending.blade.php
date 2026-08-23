@extends('admin.layout')
@section('title', 'Approval Guru')
@section('content')
    <div class="panel">
        <div class="panel-header">Akun Guru Menunggu Persetujuan</div>
        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Daftar Sejak</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($teachers as $t)
                    <tr>
                        <td>{{ $t->name }}</td>
                        <td>{{ $t->email }}</td>
                        <td>{{ $t->created_at->diffForHumans() }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.teachers.approve', $t->id) }}" style="display:inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">Setujui</button>
                            </form>
                            <form method="POST" action="{{ route('admin.teachers.reject', $t->id) }}" style="display:inline-block"
                                  onsubmit="return confirm('Tolak akun guru {{ $t->name }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger">Tolak</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Tidak ada akun guru yang menunggu persetujuan 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection