@extends('guru.layout')
@section('title', 'Nilai Proyek')
@section('content')
<div class="panel" style="padding:24px;max-width:960px;margin:auto;">
    <h2>{{ $project->title }}</h2>
    <p>Siswa: {{ $project->user->name }} · Topik: {{ $project->topic->title ?? '-' }} · {{ $project->level }}</p>
    <p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $project->description }}</p>
    <h3>Lampiran karya</h3>
    @forelse($project->formattedAttachments() as $file)
        <section style="border:1px solid var(--border);padding:16px;margin-bottom:16px;border-radius:12px;overflow-wrap:anywhere">
            <strong>{{ $file['name'] }}</strong>
            @if($file['size']) <small>({{ number_format($file['size'] / 1048576, 1) }} MB)</small> @endif
            <div style="margin:12px 0">
                @if(str_starts_with($file['mime_type'] ?? '', 'image/'))
                    <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy" style="max-width:100%;max-height:480px;object-fit:contain">
                @elseif(str_starts_with($file['mime_type'] ?? '', 'video/'))
                    <video controls preload="metadata" style="width:100%;max-height:480px" src="{{ $file['url'] }}">Video tidak didukung. Gunakan tautan unduh.</video>
                @elseif(str_starts_with($file['mime_type'] ?? '', 'audio/'))
                    <audio controls preload="metadata" style="width:100%" src="{{ $file['url'] }}">Audio tidak didukung. Gunakan tautan unduh.</audio>
                @endif
            </div>
            <a href="{{ $file['url'] }}" target="_blank" rel="noopener">Buka / unduh lampiran</a>
        </section>
    @empty
        <p>Tugas berupa jawaban teks, tanpa lampiran.</p>
    @endforelse
    <h3>Rubrik penilaian</h3>
    <p>Nilai berdasarkan tujuan tugas dan materi mata pelajaran. Gunakan jawaban serta seluruh lampiran sebagai bukti pemahaman, proses, dan hasil; format multimedia bukan syarat memperoleh nilai tinggi.</p>
    <form method="POST" action="{{ route('guru.projects.grade', $project->id) }}">
        @csrf
        @foreach(\App\Models\PblProject::rubricCriteria() as $key => $criterion)
            <fieldset style="margin:16px 0;padding:16px;border-radius:12px;border:1px solid var(--border)">
                <legend>{{ $criterion['label'] }} · {{ $criterion['weight'] }}%</legend>
                <p>{{ $criterion['description'] }}</p>
                <label for="score-{{ $key }}">Nilai (0–100)</label>
                <input id="score-{{ $key }}" data-weight="{{ $criterion['weight'] }}" type="number" name="rubric_scores[{{ $key }}]" min="0" max="100" step="1" value="{{ old('rubric_scores.'.$key) }}" required>
                <label for="feedback-{{ $key }}">Catatan kriteria (opsional)</label>
                <textarea id="feedback-{{ $key }}" name="rubric_feedback[{{ $key }}]" rows="2" maxlength="2000">{{ old('rubric_feedback.'.$key) }}</textarea>
            </fieldset>
        @endforeach
        <p><strong>Nilai akhir: <output id="total-score">—</output></strong></p>
        <label for="feedback">Feedback untuk siswa</label>
        <textarea id="feedback" name="feedback" rows="4" maxlength="2000" required>{{ old('feedback') }}</textarea>
        <button type="submit" class="btn btn-primary">Simpan Penilaian</button>
    </form>
</div>
<script>
const scores = [...document.querySelectorAll('[data-weight]')];
function updateScore() {
    document.getElementById('total-score').textContent = scores.every(s => s.value !== '' && s.validity.valid)
        ? scores.reduce((sum, s) => sum + Number(s.value) * Number(s.dataset.weight) / 100, 0).toFixed(2) : '—';
}
scores.forEach(s => s.addEventListener('input', updateScore));
updateScore();
</script>
@endsection
