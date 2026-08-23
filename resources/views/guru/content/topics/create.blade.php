@extends('guru.layout')
@section('title', 'Buat Topik')
@section('content')
    <div class="panel" style="padding:20px; max-width:560px;">
        <form method="POST" action="{{ route('guru.content.topics.store', $subject->id) }}">
            @csrf
            <div class="form-group">
                <label>Judul Topik</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title') }}">
            </div>
            <div class="form-group">
                <label>Deskripsi (opsional)</label>
                <textarea name="description" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label>Urutan (opsional — kosongkan untuk otomatis di akhir)</label>
                <input type="number" name="order" min="1" value="{{ old('order') }}">
            </div>
            <button type="submit" class="btn btn-primary">Buat Topik</button>
        </form>
    </div>
@endsection