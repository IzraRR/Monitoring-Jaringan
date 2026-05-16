<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Admin Jaringan SMKN 53</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; }
        .login-box { margin-top: 10vh; }
    </style>
</head>
<body>

<div class="container login-box">
    <div class="row justify-content-center">
        <div class="col-md-4">
            @if (session('error'))
                <div class="alert alert-danger text-center">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger text-center">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="mb-0 fw-bold">LOGIN | ADMIN JARINGAN</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('login.attempt') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username..." value="{{ old('username') }}" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan password..." required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark fw-bold py-2">MASUK</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3 text-white-50">
                <small>&copy; 2026 SMKN 53 Jakarta</small>
            </div>

        </div>
    </div>
</div>

</body>
</html>
