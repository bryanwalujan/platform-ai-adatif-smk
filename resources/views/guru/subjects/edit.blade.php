@extends('guru.layout')
@section('title', 'Edit Mata Pelajaran')
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <form method="POST" action="{{ route('guru.subjects.update', $subject->id) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Nama Mata Pelajaran</label>
                <input type="text" name="name" maxlength="255" required value="{{ old('name', $subject->name) }}">
            </div>
            <div class="form-group">
                <label>Deskripsi (opsional)</label>
                <textarea name="description" rows="3">{{ old('description', $subject->description) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection