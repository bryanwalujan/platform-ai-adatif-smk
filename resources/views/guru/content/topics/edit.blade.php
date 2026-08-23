@extends('guru.layout')
@section('title', 'Edit Topik')
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <form method="POST" action="{{ route('guru.content.topics.update', $topic->id) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Judul Topik</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title', $topic->title) }}">
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" rows="3">{{ old('description', $topic->description) }}</textarea>
            </div>
            <div class="form-group">
                <label>Urutan</label>
                <input type="number" name="order" min="1" value="{{ old('order', $topic->order) }}">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection