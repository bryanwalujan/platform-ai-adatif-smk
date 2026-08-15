@extends('admin.layout')

@section('title', 'Approval Guru')

@section('content')
    <div class="panel">
        <div class="panel-header">Akun Guru Menunggu Persetujuan ({{ $teachers->count() }})</div>

        @if ($teachers->isEmpty())
            <div class="empty">Tidak ada akun guru yang menunggu persetujuan saat ini.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Daftar Pada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teachers as $teacher)
                        <tr>
                            <td>{{ $teacher->name }}</td>
                            <td>{{ $teacher->email }}</td>
                            <td>{{ $teacher->created_at?->format('d M Y, H:i') ?? '-' }}</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.teachers.approve', $teacher->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.teachers.reject', $teacher->id) }}"
                                      onsubmit="return confirm('Tolak pendaftaran {{ $teacher->name }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
