@extends('guru.layout')
@section('title', 'Tambah Soal')
@php($breadcrumbs = [
    $quiz->topic->subject->name => route('guru.subjects.show', $quiz->topic->subject_id),
    $quiz->topic->title => route('guru.content.topics.show', $quiz->topic_id),
    $quiz->title => route('guru.content.quizzes.show', $quiz->id),
    'Tambah Soal' => null,
])
@section('content')
    <div class="panel" style="padding:20px; max-width:640px;">
        <p style="color:var(--text-muted); font-size:13px; margin-top:0;">Kuis: {{ $quiz->title }} ({{ $quiz->topic->title }})</p>

        <form method="POST" action="{{ route('guru.content.questions.store', $quiz->id) }}">
            @csrf

            <div class="form-group">
                <label>Pertanyaan</label>
                <textarea name="question" rows="3" required>{{ old('question') }}</textarea>
            </div>

            <div class="form-group">
                <label>Pilihan Jawaban (isi minimal 2, tandai jawaban yang benar)</label>
                @for($i = 0; $i < 4; $i++)
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <input type="radio" name="correct_option" value="{{ $i }}" required>
                        <input type="text" name="options[]" placeholder="Pilihan {{ $i + 1 }}{{ $i < 2 ? ' (wajib)' : ' (opsional)' }}"
                               value="{{ old('options.' . $i) }}" style="flex:1; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:13px;"
                               {{ $i < 2 ? 'required' : '' }}>
                    </div>
                @endfor
                <p style="font-size:12px; color:var(--text-muted);">Pilih radio button di sebelah kiri pilihan yang merupakan jawaban benar.</p>
            </div>

            <div class="form-group">
                <label>Penjelasan Jawaban (opsional)</label>
                <textarea name="explanation" rows="2">{{ old('explanation') }}</textarea>
            </div>

            {{-- correct_answer diisi otomatis dari teks opsi yang radio-nya dipilih,
                 karena Api\ContentController menyimpan correct_answer sebagai STRING
                 (isi teksnya), bukan index — lihat storeQuizQuestion(). --}}
            <input type="hidden" name="correct_answer" id="correct_answer_field">

            <button type="submit" class="btn btn-primary" onclick="return setCorrectAnswer()">Simpan Soal</button>
        </form>
    </div>

    <script>
        function setCorrectAnswer() {
            const form = document.querySelector('form');
            const selected = form.querySelector('input[name="correct_option"]:checked');

            if (!selected) {
                alert('Pilih salah satu opsi sebagai jawaban benar.');
                return false;
            }

            const optionInputs = form.querySelectorAll('input[name="options[]"]');
            const correctText = optionInputs[selected.value].value.trim();

            if (!correctText) {
                alert('Pilihan yang ditandai sebagai jawaban benar tidak boleh kosong.');
                return false;
            }

            document.getElementById('correct_answer_field').value = correctText;
            return true;
        }
    </script>
@endsection