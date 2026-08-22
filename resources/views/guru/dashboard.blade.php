{{-- resources/views/guru/dashboard.blade.php --}}
@extends('guru.layout')
@section('title', 'Dashboard')
@section('content')
    <div class="cards">
        <div class="card"><div class="num">{{ $totalStudents }}</div><div class="label">Total Siswa</div></div>
        <div class="card"><div class="num">{{ $totalProjects }}</div><div class="label">Total Proyek PBL</div></div>
        <div class="card"><div class="num">{{ $pendingProjects }}</div><div class="label">Menunggu Penilaian</div></div>
        <div class="card"><div class="num">{{ $totalTopics }}</div><div class="label">Total Topik</div></div>
    </div>

    @if($pendingProjects > 0)
        <div class="panel">
            <div class="panel-header">
                Ada {{ $pendingProjects }} proyek menunggu dinilai
                <a href="{{ route('guru.projects.pending') }}" class="btn btn-primary btn-sm">Nilai Sekarang</a>
            </div>
        </div>
    @endif
@endsection