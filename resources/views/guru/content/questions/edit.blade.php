@extends('guru.layout')
@section('title', 'Edit Soal')
@php($breadcrumbs = [
    $question->quiz->topic->subject->name => route('guru.subjects.show', $question->quiz->topic->subject_id),
    $question->quiz->topic->title => route('guru.content.topics.show', $question->quiz->topic_id),
    $question->quiz->title => route('guru.content.quizzes.show', $question->quiz_id),
    'Edit Soal' => null,
])
@section('content')
    @php
        $currentOptions = is_string($question->options) ? json_decode($question->options, true) : ($question->options ?? []);
    @endphp

    <div class="panel" style="padding:20px; max-width:640px;">
        <form method="POST" action="{{ route('guru.content.questions.update', $question->id) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Pertanyaan</label>
                <textarea name="question" rows="3" required>{{ old('question', $question->question) }}</textarea>
            </div>

            <div class="form-group">
                <label>Pilihan Jawaban (tandai jawaban yang benar)</label>
                @for($i = 0; $i < 4; $i++)
                    @php $optValue = old('options.' . $i, $currentOptions[$i] ?? ''); @endphp
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <input type="radio" name="correct_option" value="{{ $i }}"
                               {{ $optValue === $question->correct_answer ? 'checked' : '' }} required>
                        <input type="text" name="options[]" placeholder="Pilihan {{ $i + 1 }}{{ $i < 2 ? ' (wajib)' : ' (opsional)' }}"
                               value="{{ $optValue }}" style="flex:1; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:13px;"
                               {{ $i < 2 ? 'required' : '' }}>
                    </div>
                @endfor
            </div>

            <div class="form-group">
                <label>Penjelasan Jawaban (opsional)</label>
                <textarea name="explanation" rows="2">{{ old('explanation', $question->explanation) }}</textarea>
            </div>

            <input type="hidden" name="correct_answer" id="correct_answer_field">

            <button type="submit" class="btn btn-primary" onclick="return setCorrectAnswer()">Simpan Perubahan</button>
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