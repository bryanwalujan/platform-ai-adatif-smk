@extends('guru.layout')
@section('title', $plan->title)
@section('content')
    <div style="margin-bottom:16px;">
        <a href="{{ route('guru.lesson-plans.index', $plan->subject_id) }}" class="btn btn-sm">&larr; Kembali ke RPP</a>
        <a href="{{ route('guru.lesson-plans.edit', $plan->id) }}" class="btn btn-sm">Edit</a>
        <form method="POST" action="{{ route('guru.lesson-plans.toggle-complete', $plan->id) }}" style="display:inline-block">
            @csrf
            <button type="submit" class="btn btn-sm {{ $plan->is_completed ? '' : 'btn-primary' }}">
                {{ $plan->is_completed ? 'Tandai Belum Selesai' : 'Tandai Selesai' }}
            </button>
        </form>
        <form method="POST" action="{{ route('guru.lesson-plans.destroy', $plan->id) }}" style="display:inline-block"
              onsubmit="return confirm('Hapus RPP ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
        </form>
    </div>

    <div class="panel" style="padding:20px;">
        <p style="color:var(--text-muted); font-size:13px; margin-top:0;">
            Pertemuan ke-{{ $plan->meeting_number }} &middot;
            {{ $plan->subject->name }} &middot;
            @if($plan->topic) Topik: {{ $plan->topic->title }} &middot; @endif
            {{ $plan->scheduled_date ? \Carbon\Carbon::parse($plan->scheduled_date)->format('d/m/Y') : 'Belum dijadwalkan' }}
            &middot;
            <span class="badge {{ $plan->is_completed ? 'badge-active' : 'badge-pending' }}">
                {{ $plan->is_completed ? 'Selesai' : 'Belum' }}
            </span>
        </p>

        @if($plan->learning_objective)
            <h3>Tujuan Pembelajaran</h3>
            <p>{{ $plan->learning_objective }}</p>
        @endif

        @if($plan->description)
            <h3>Langkah Kegiatan</h3>
            <p style="white-space:pre-line;">{{ $plan->description }}</p>
        @endif

        @if($plan->file_path)
            <h3>Lampiran</h3>
            <p><a href="{{ url('/api/files/' . $plan->file_path) }}" target="_blank">📎 {{ $plan->file_name }}</a></p>
        @endif
    </div>
@endsection