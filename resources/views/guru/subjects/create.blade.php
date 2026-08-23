@extends('guru.layout')
@section('title', 'Buat Mata Pelajaran')
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <form method="POST" action="{{ route('guru.subjects.store') }}">
            @csrf
            <div class="form-group">
                <label>Nama Mata Pelajaran</label>
                <input type="text" name="name" maxlength="255" required value="{{ old('name') }}">
            </div>
            <div class="form-group">
                <label>Deskripsi (opsional)</label>
                <textarea name="description" rows="3">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Buat Mata Pelajaran</button>
        </form>
    </div>
@endsection