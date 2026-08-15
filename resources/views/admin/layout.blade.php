<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Panel Admin</title>
    <style>
        :root {
            --bg: #f4f5f7;
            --surface: #ffffff;
            --border: #e2e4e9;
            --text: #1f2430;
            --text-muted: #6b7280;
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success-bg: #ecfdf5;
            --success-text: #047857;
            --error-bg: #fef2f2;
            --error-text: #b91c1c;
            --pending-bg: #fffbeb;
            --pending-text: #b45309;
            --sidebar-w: 220px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .app { display: flex; min-height: 100vh; }

        .sidebar {
            width: var(--sidebar-w);
            background: #171a23;
            color: #cbd0dc;
            flex-shrink: 0;
            padding: 20px 0;
        }
        .sidebar .brand {
            padding: 0 20px 20px;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            border-bottom: 1px solid #2a2f3c;
            margin-bottom: 12px;
        }
        .sidebar .brand small { display: block; font-weight: 400; color: #8a90a3; font-size: 12px; margin-top: 2px; }
        .sidebar nav a {
            display: block;
            padding: 10px 20px;
            color: #cbd0dc;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar nav a:hover { background: #1f232e; text-decoration: none; }
        .sidebar nav a.active { background: #1f232e; border-left-color: var(--primary); color: #fff; font-weight: 600; }

        .main { flex: 1; min-width: 0; }
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar h1 { font-size: 18px; margin: 0; }
        .topbar form { margin: 0; }
        .btn {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
        }
        .btn:hover { background: var(--bg); text-decoration: none; }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--error-bg); border-color: #fecaca; color: var(--error-text); }
        .btn-danger:hover { background: #fee2e2; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .content { padding: 24px 28px; }

        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .flash-success { background: var(--success-bg); color: var(--success-text); border: 1px solid #a7f3d0; }
        .flash-error { background: var(--error-bg); color: var(--error-text); border: 1px solid #fecaca; }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }
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
        .badge-active { background: var(--success-bg); color: var(--success-text); }
        .badge-pending { background: var(--pending-bg); color: var(--pending-text); }
        .badge-rejected { background: var(--error-bg); color: var(--error-text); }
        .badge-guru { background: #eef2ff; color: #4338ca; }
        .badge-siswa { background: #f0f9ff; color: #0369a1; }
        .badge-admin { background: #1f2430; color: #fff; }

        .empty { padding: 40px 18px; text-align: center; color: var(--text-muted); }

        .filters { display: flex; gap: 8px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
        .filters input, .filters select {
            padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;
        }

        code.join-code {
            background: #eef2ff; color: #4338ca; padding: 3px 8px; border-radius: 6px;
            font-family: Menlo, Consolas, monospace; font-weight: 600; letter-spacing: .04em;
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                Panel Admin
                <small>SMK Multi-Mapel</small>
            </div>
            <nav>
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.teachers.pending') }}" class="{{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}">
                    Approval Guru
                    @isset($guruPendingBadge)
                        @if($guruPendingBadge > 0) ({{ $guruPendingBadge }}) @endif
                    @endisset
                </a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Semua User</a>
                <a href="{{ route('admin.subjects.index') }}" class="{{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}">Mata Pelajaran</a>
            </nav>
        </aside>

        <div class="main">
            <div class="topbar">
                <h1>@yield('title', 'Dashboard')</h1>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn">Logout ({{ auth()->user()->name }})</button>
                </form>
            </div>

            <div class="content">
                @if (session('success'))
                    <div class="flash flash-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="flash flash-error">{{ session('error') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
