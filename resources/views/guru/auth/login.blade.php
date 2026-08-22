{{-- resources/views/guru/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Guru</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f4f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { background: #fff; padding: 32px; border-radius: 12px; border: 1px solid #e2e4e9; width: 340px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p.sub { color: #6b7280; font-size: 13px; margin: 0 0 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        input { width: 100%; padding: 9px 10px; border: 1px solid #e2e4e9; border-radius: 6px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; border: none; border-radius: 6px; background: #0e7490; color: #fff; font-size: 14px; cursor: pointer; }
        .err { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Panel Guru</h1>
        <p class="sub">Masuk dengan akun guru yang sudah disetujui admin.</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('guru.login.submit') }}">
            @csrf
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>