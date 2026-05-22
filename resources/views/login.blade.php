<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Admin Jaringan SMKN 53</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #17395f 100%);
            min-height: 100vh;
        }
        .login-box { margin-top: 8vh; }
        .login-card { border-radius: 16px; overflow: hidden; }
        .login-header { background: #17395f; }
    </style>
</head>
<body>

<div class="container login-box">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            @if (session('success'))
                <div class="alert alert-success text-center">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger text-center">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger text-center">{{ $errors->first() }}</div>
            @endif

            <div class="card login-card shadow-lg border-0">
                <div class="card-header login-header text-white text-center py-4">
                    <i class="bi bi-router fs-1 d-block mb-2"></i>
                    <h5 class="mb-0 fw-bold">MONITORING JARINGAN</h5>
                    <small class="opacity-75">SMKN 53 Jakarta</small>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('login.attempt') }}" method="POST" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Masukkan username" value="{{ old('username') }}" required autofocus autocomplete="username">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="login-password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="login-password" tabindex="-1" aria-label="Tampilkan sandi">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark fw-bold py-2">MASUK</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3 text-white-50">
                <small>&copy; {{ date('Y') }} SMKN 53 Jakarta</small>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            }
        });
    });
</script>
</body>
</html>
