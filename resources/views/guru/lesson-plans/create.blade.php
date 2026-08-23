@extends('guru.layout')
@section('title', 'Buat RPP')
@section('content')
    <div class="panel" style="padding:20px; max-width:640px;">
        <form method="POST" action="{{ route('guru.lesson-plans.store', $subject->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Nomor Pertemuan</label>
                <input type="number" name="meeting_number" min="1" required value="{{ old('meeting_number') }}">
            </div>
            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title') }}">
            </div>
            <div class="form-group">
                <label>Topik Terkait (opsional)</label>
                <select name="topic_id">
                    <option value="">— Tidak terikat topik —</option>
                    @foreach($topics as $t)
                        <option value="{{ $t->id }}" @selected(old('topic_id') == $t->id)>{{ $t->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Tujuan Pembelajaran (opsional)</label>
                <textarea name="learning_objective" rows="3">{{ old('learning_objective') }}</textarea>
            </div>
            <div class="form-group">
                <label>Deskripsi / Langkah Kegiatan (opsional)</label>
                <textarea name="description" rows="4">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label>Tanggal Pelaksanaan (opsional)</label>
                <input type="date" name="scheduled_date" value="{{ old('scheduled_date') }}">
            </div>
            <div class="form-group">
                <label>Lampiran File (opsional — PDF, Word, PPT, gambar, maks 15MB)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
            </div>
            <button type="submit" class="btn btn-primary">Simpan RPP</button>
        </form>
    </div>
@endsection