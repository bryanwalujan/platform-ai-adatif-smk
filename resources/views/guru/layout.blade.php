{{-- resources/views/guru/layout.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Guru') — Panel Guru</title>
    <style>
        {{-- sengaja pakai style block persis sama dengan admin.layout supaya
             konsisten visual — kalau nanti dipindah ke CSS file bersama,
             tinggal @vite satu file untuk keduanya. --}}
        :root {
            --bg: #f4f5f7; --surface: #fff; --border: #e2e4e9; --text: #1f2430;
            --text-muted: #6b7280; --primary: #0e7490; --primary-dark: #155e75;
            --success-bg: #ecfdf5; --success-text: #047857;
            --error-bg: #fef2f2; --error-text: #b91c1c;
            --pending-bg: #fffbeb; --pending-text: #b45309; --sidebar-w: 220px;
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:var(--bg); color:var(--text); font-size:14px; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .app { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-w); background: #0f2b32; color: #cbd9db; flex-shrink: 0; padding: 20px 0; }
        .sidebar .brand { padding: 0 20px 20px; font-weight: 700; font-size: 16px; color: #fff; border-bottom: 1px solid #1c3d44; margin-bottom: 12px; }
        .sidebar .brand small { display: block; font-weight: 400; color: #7fa1a6; font-size: 12px; margin-top: 2px; }
        .sidebar nav a { display: block; padding: 10px 20px; color: #cbd9db; border-left: 3px solid transparent; }
        .sidebar nav a:hover { background: #123840; text-decoration: none; }
        .sidebar nav a.active { background: #123840; border-left-color: var(--primary); color: #fff; font-weight: 600; }
        .main { flex: 1; min-width: 0; }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .topbar h1 { font-size: 18px; margin: 0; }
        .btn { display: inline-block; padding: 7px 14px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 13px; cursor: pointer; }
        .btn:hover { background: var(--bg); text-decoration: none; }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .content { padding: 24px 28px; }
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .flash-success { background: var(--success-bg); color: var(--success-text); border: 1px solid #a7f3d0; }
        .flash-error { background: var(--error-bg); color: var(--error-text); border: 1px solid #fecaca; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 18px; }
        .card .num { font-size: 26px; font-weight: 700; }
        .card .label { color: var(--text-muted); font-size: 12px; margin-top: 4px; }
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .panel-header { padding: 14px 18px; border-bottom: 1px solid var(--border); font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        td.actions { white-space: nowrap; text-align: right; }
        td.actions form { display: inline-block; margin-left: 6px; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-submitted { background: var(--pending-bg); color: var(--pending-text); }
        .badge-graded { background: var(--success-bg); color: var(--success-text); }
        .empty { padding: 40px 18px; text-align: center; color: var(--text-muted); }
        .filters { display: flex; gap: 8px; padding: 14px 18px; border-bottom: 1px solid var(--border); align-items: center; }
        .filters input, .filters select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; }
        .subject-switcher { font-size: 13px; }
        .subject-switcher select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; font-family: inherit; }
        .rubric-row { display: grid; grid-template-columns: 1fr 100px; gap: 10px; align-items: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">Panel Guru<small>SMK Multi-Mapel</small></div>
            <nav>
                <a href="{{ route('guru.dashboard') }}" class="{{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('guru.students.index') }}" class="{{ request()->routeIs('guru.students.*') ? 'active' : '' }}">Siswa</a>
                <a href="{{ route('guru.projects.pending') }}" class="{{ request()->routeIs('guru.projects.pending') ? 'active' : '' }}">Proyek Belum Dinilai</a>
                <a href="{{ route('guru.projects.index') }}" class="{{ request()->routeIs('guru.projects.index') || request()->routeIs('guru.projects.grade.form') ? 'active' : '' }}">Semua Proyek</a>
                <a href="{{ route('guru.subjects.index') }}" class="{{ request()->routeIs('guru.subjects.*') ? 'active' : '' }}">Mata Pelajaran Saya</a>
            </nav>
        </aside>

        <div class="main">
            <div class="topbar">
                <h1>@yield('title', 'Dashboard')</h1>

                <div style="display:flex; align-items:center; gap:14px;">
                    @isset($subjectOptions)
                        @if($subjectOptions->count() > 1)
                            <form method="GET" class="subject-switcher">
                                <select name="subject_id" onchange="this.form.submit()">
                                    <option value="">Semua mapel saya</option>
                                    @foreach($subjectOptions as $s)
                                        <option value="{{ $s->id }}" @selected((string) $currentSubjectId === (string) $s->id)>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                    @endisset

                    <form method="POST" action="{{ route('guru.logout') }}">
                        @csrf
                        <button type="submit" class="btn">Logout ({{ auth()->user()->name }})</button>
                    </form>
                </div>
            </div>

            <div class="content">
                @if (session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
                @if (session('error'))<div class="flash flash-error">{{ session('error') }}</div>@endif
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>