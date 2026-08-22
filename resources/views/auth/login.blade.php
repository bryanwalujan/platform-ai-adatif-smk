<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #171a23;
        }
        .box {
            background: #fff;
            border-radius: 12px;
            padding: 36px 32px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 20px 40px rgba(0,0,0,.25);
        }
        .box h1 { font-size: 18px; margin: 0 0 4px; }
        .box p.sub { color: #6b7280; font-size: 13px; margin: 0 0 24px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input {
            width: 100%; padding: 10px 12px; border: 1px solid #e2e4e9; border-radius: 8px;
            font-size: 14px; margin-bottom: 16px; box-sizing: border-box;
        }
        input:focus { outline: 2px solid #4f46e5; outline-offset: 1px; }
        button {
            width: 100%; padding: 11px; border: none; border-radius: 8px;
            background: #4f46e5; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        button:hover { background: #4338ca; }
        .errors { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Masuk ke Panel</h1>
        <p class="sub">Untuk admin & guru. Sistem akan mengarahkan Anda otomatis sesuai peran akun.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>