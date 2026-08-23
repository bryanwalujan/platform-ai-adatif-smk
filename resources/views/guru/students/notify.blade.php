{{-- resources/views/guru/students/notify.blade.php --}}
@extends('guru.layout')
@section('title', 'Kirim Notifikasi')
@section('content')
    <div class="panel" style="padding: 20px; max-width: 480px;">
        <p style="margin-top:0; color:var(--text-muted); font-size:13px;">
            Kirim notifikasi ke <strong>{{ $student->name }}</strong> ({{ $student->email }})
        </p>

        <form method="POST" action="{{ route('guru.students.notify', $student->id) }}">
            @csrf

            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title') }}">
            </div>

            <div class="form-group">
                <label>Pesan</label>
                <textarea name="message" rows="4" required>{{ old('message') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Kirim Notifikasi</button>
        </form>
    </div>
@endsection