@extends('guru.layout')
@section('title', 'Edit RPP')
@section('content')
    <div class="panel" style="padding:20px; max-width:640px;">
        <form method="POST" action="{{ route('guru.lesson-plans.update', $plan->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Nomor Pertemuan</label>
                <input type="number" name="meeting_number" min="1" required value="{{ old('meeting_number', $plan->meeting_number) }}">
            </div>
            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="title" maxlength="255" required value="{{ old('title', $plan->title) }}">
            </div>
            <div class="form-group">
                <label>Topik Terkait (opsional)</label>
                <select name="topic_id">
                    <option value="">— Tidak terikat topik —</option>
                    @foreach($topics as $t)
                        <option value="{{ $t->id }}" @selected(old('topic_id', $plan->topic_id) == $t->id)>{{ $t->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Tujuan Pembelajaran</label>
                <textarea name="learning_objective" rows="3">{{ old('learning_objective', $plan->learning_objective) }}</textarea>
            </div>
            <div class="form-group">
                <label>Deskripsi / Langkah Kegiatan</label>
                <textarea name="description" rows="4">{{ old('description', $plan->description) }}</textarea>
            </div>
            <div class="form-group">
                <label>Tanggal Pelaksanaan</label>
                <input type="date" name="scheduled_date" value="{{ old('scheduled_date', $plan->scheduled_date) }}">
            </div>
            @if($plan->file_path)
                <p style="font-size:13px;">File saat ini: <a href="{{ url('/api/files/' . $plan->file_path) }}" target="_blank">{{ $plan->file_name }}</a></p>
            @endif
            <div class="form-group">
                <label>Ganti Lampiran File (opsional — kosongkan jika tidak diganti)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection