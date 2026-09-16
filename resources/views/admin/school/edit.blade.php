@extends('admin.layout')
@section('title', 'Pengaturan Sekolah')
@section('content')
<div class="panel" style="padding:24px;max-width:680px">
    <h2>{{ $school->name }}</h2>
    <p>Bagikan kode ini kepada guru dan siswa untuk mendaftar di sekolah Anda. Kode sekolah berbeda dari kode kelas/mata pelajaran.</p>
    <p>Kode sekolah: <code class="join-code">{{ $school->code }}</code></p>
    <form method="POST" action="{{ route('admin.school.update') }}">
        @csrf @method('PUT')
        <label for="name">Nama sekolah</label>
        <input id="name" name="name" value="{{ old('name', $school->name) }}" maxlength="255" required>
        <button class="btn btn-primary" type="submit">Simpan nama</button>
    </form>
    <hr>
    <p>Ganti kode jika tidak ingin kode sebelumnya dipakai untuk pendaftaran baru. Keanggotaan akun lama tetap berlaku.</p>
    <form method="POST" action="{{ route('admin.school.regenerate') }}">
        @csrf
        <button class="btn" type="submit">Ganti kode sekolah</button>
    </form>
</div>
@endsection
