@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
    <div class="cards">
        <div class="card"><div class="num">{{ $totalSiswa }}</div><div class="label">Total Siswa</div></div>
        <div class="card"><div class="num">{{ $totalGuru }}</div><div class="label">Total Guru</div></div>
        <div class="card"><div class="num">{{ $guruPending }}</div><div class="label">Guru Menunggu Approval</div></div>
        <div class="card"><div class="num">{{ $totalSubjects }}</div><div class="label">Total Mata Pelajaran</div></div>
        <div class="card"><div class="num">{{ $subjectsActive }}</div><div class="label">Mapel Aktif</div></div>
    </div>

    @if($guruPending > 0)
        <div class="panel">
            <div class="panel-header">
                Ada {{ $guruPending }} akun guru menunggu persetujuan
                <a href="{{ route('admin.teachers.pending') }}" class="btn btn-primary btn-sm">Tinjau Sekarang</a>
            </div>
        </div>
    @endif
@endsection