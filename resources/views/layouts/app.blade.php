<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Monitoring SMKN 53')</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --navy: #17395f;
            --navy-deep: #102845;
            --panel: #f4f8f7;
        }

        body {
            margin: 0;
            background: var(--navy-deep);
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .app-shell {
            min-height: 100vh;
            background: var(--navy-deep);
            padding: 8px;
        }

        .sidebar {
            min-height: calc(100vh - 16px);
            width: 290px;
            background: linear-gradient(180deg, #112847 0%, #10233d 100%);
            border-radius: 22px;
            position: sticky;
            top: 8px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
        }

        .sidebar-logo {
            padding: 22px 18px 12px;
            text-align: center;
            color: white;
        }

        .brand-logo-image {
            display: block;
            object-fit: contain;
            margin: 0 auto 8px;
        }

        .sidebar-logo .brand-shield {
            width: 68px;
            height: 86px;
            margin: 0 auto 8px;
            background: transparent;
            clip-path: none;
        }

        .sidebar-logo .brand-logo-image {
            width: 74px;
            height: 86px;
        }

        .sidebar-logo h5 {
            line-height: 0.95;
            letter-spacing: 0.5px;
            font-size: 2rem;
        }

        .sidebar-menu {
            padding: 10px 0 18px;
        }

        .sidebar .nav-link {
            color: #e8eef7;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1.05rem;
            border-radius: 0 12px 12px 0;
            margin: 4px 0;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link i {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.28);
            color: #ffffff;
        }

        .sidebar .nav-link.active {
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15);
        }

        .main-content {
            margin-left: 0;
            padding: 0;
            width: auto;
            min-height: calc(100vh - 16px);
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
        }

        .top-navbar {
            height: 120px;
            background: var(--navy);
            color: white;
            border-radius: 0 0 18px 18px;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }

        .brand-title {
            display: flex;
            align-items: center;
            gap: 18px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            margin-left: 0;
        }

        .brand-title .brand-logo-image {
            width: 82px;
            height: 96px;
        }

        .brand-title .brand-shield {
            width: 78px;
            height: 96px;
            background: transparent;
            clip-path: none;
        }

        .brand-title h1 {
            margin: 0;
            font-size: 2.2rem;
            line-height: 0.92;
            font-weight: 800;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.15rem;
            padding-top: 8px;
            margin-left: auto;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }

        .user-badge .avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.14);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.45);
        }

        .content-panel {
            background: var(--panel);
            border-radius: 18px;
            margin: 12px 0 8px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
            flex: 1;
        }

        .top-helper {
            display: flex;
            justify-content: center;
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.92rem;
            font-weight: 700;
        }

        .page-footer {
            background: var(--navy);
            color: #ffffff;
            border-radius: 18px 18px 0 0;
            padding: 10px 20px;
            text-align: center;
            font-weight: 700;
            margin: 0;
        }

        @media (max-width: 991.98px) {
            .app-shell {
                padding: 0;
            }

            .sidebar {
                position: static;
                width: auto;
                min-height: auto;
                border-radius: 0;
                margin-bottom: 12px;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                min-height: auto;
            }

            .top-navbar {
                height: auto;
                flex-direction: column;
                gap: 12px;
                position: static;
            }

            .brand-title {
                position: static;
                transform: none;
            }

            .content-panel,
            .page-footer {
                margin-left: 0;
                margin-right: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
@php
    $logoPath = asset('logo.png');
@endphp

<div class="app-shell">
    <div class="d-flex gap-0 flex-column flex-lg-row">
        <button class="btn btn-light d-lg-none mx-2 mt-2 align-self-start" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-expanded="false" aria-controls="sidebarMenu">
            <i class="bi bi-list"></i> Menu
        </button>
        <aside id="sidebarMenu" class="sidebar collapse d-lg-block">
            <div class="sidebar-menu">
                @if(session('admin_role') === 'Admin')
                <a href="{{ url('/dashboard') }}" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-fill"></i><span>Dashboard</span>
                </a>
                <a href="{{ url('/pelanggan') }}" class="nav-link {{ request()->is('pelanggan*') ? 'active' : '' }}">
                    <i class="bi bi-person-fill"></i><span>Kelola Pelanggan</span>
                </a>
                <a href="{{ url('/bandwidth') }}" class="nav-link {{ request()->is('bandwidth*') ? 'active' : '' }}">
                    <i class="bi bi-activity"></i><span>Manajemen Bandwidth</span>
                </a>
                <a href="{{ url('/password') }}" class="nav-link {{ request()->is('password') ? 'active' : '' }}">
                    <i class="bi bi-lock-fill"></i><span>Ubah Password</span>
                </a>
                <a href="{{ url('/pembayaran') }}" class="nav-link {{ request()->is('pembayaran*') ? 'active' : '' }}">
                    <i class="bi bi-credit-card-fill"></i><span>Pencatatan Pembayaran</span>
                </a>
                <a href="{{ url('/log') }}" class="nav-link {{ request()->is('log') ? 'active' : '' }}">
                    <i class="bi bi-list-ul"></i><span>Log Aktivitas</span>
                </a>
                @endif
                <a href="{{ url('/laporan') }}" class="nav-link {{ request()->is('laporan') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text-fill"></i><span>Laporan</span>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                        <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <div class="top-navbar">
                <div class="brand-title">
                    <img src="{{ $logoPath }}" alt="Logo SMKN 53" class="brand-logo-image">
                    <h1>SMKN 53<br>JAKARTA</h1>
                </div>

                <div class="user-badge">
                    <span class="avatar"><i class="bi bi-person fs-4"></i></span>
                    <span>{{ session('admin_nama', 'Admin_Budi') }}</span>
                </div>
            </div>

            <div class="content-panel">
                @include('partials.flash-messages')
                @yield('content')
            </div>

            <div class="page-footer">© 2026 SMKN 53 Jakarta</div>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
@include('partials.traffic-alert-utils')
@include('partials.traffic-alert-monitor')
@stack('scripts')
</body>
</html>