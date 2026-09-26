<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        body { min-height: 100vh; display: grid; place-items: center; background: #0f172a; padding: 1rem; }
        body::before { content: ''; position: fixed; inset: -20%; background:
            radial-gradient(600px 400px at 15% 20%, rgba(59,130,246,.25), transparent 60%),
            radial-gradient(700px 500px at 85% 80%, rgba(99,102,241,.22), transparent 60%);
            animation: drift 14s ease-in-out infinite alternate;
        }
        @keyframes drift { to { transform: translate(3%, 4%) scale(1.05); } }
        .login-card { position: relative; width: 100%; max-width: 400px; background: #fff; border-radius: 20px; padding: 2.5rem 2rem; box-shadow: 0 32px 80px -20px rgba(0,0,0,.5); }
        .login-brand { display: flex; align-items: center; gap: .8rem; margin-bottom: 1.75rem; }
        .login-brand .logo { width: 46px; height: 46px; border-radius: 13px; background: linear-gradient(135deg, #3b82f6, #6366f1); display: grid; place-items: center; color: #fff; font-size: 1.2rem; }
        .login-brand b { font-size: 1.1rem; letter-spacing: -.02em; display: block; }
        .login-brand small { color: #94a3b8; font-size: .74rem; }
        .form-label { font-size: .8rem; font-weight: 600; color: #475569; }
        .form-control { border-radius: 11px; border-color: #e2e8f0; padding: .65rem .9rem; font-size: .9rem; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 .2rem rgba(37,99,235,.14); }
        .btn-login { background: #2563eb; border: 0; border-radius: 11px; padding: .7rem; font-weight: 700; font-size: .92rem; width: 100%; }
        .btn-login:hover { background: #1d4ed8; }
        .input-group-text { border-radius: 11px 0 0 11px; background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }
        .input-group .form-control { border-radius: 0 11px 11px 0; }
        .alert-danger { border: 0; border-radius: 11px; font-size: .84rem; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-brand">
        <div class="logo"><i class="fa-solid fa-store"></i></div>
        <div><b>{{ config('app.name') }}</b><small>Sistem Manajemen Franchise</small></div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <span>{{ $errors->first() }}</span>
    </div>
    @endif

    <form method="post" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-regular fa-key"></i></span>
                <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
            </div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label small" for="remember" style="font-size:.83rem;color:#64748b">Ingat saya</label>
        </div>
        <button class="btn btn-login" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i>Masuk</button>
    </form>
</div>
</body>
</html>
