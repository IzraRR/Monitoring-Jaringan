<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->session()->get('admin_role');

        if ($role === 'Admin') {
            return $next($request);
        }

        // List of allowed route names for Owner/Kepsek
        $allowedRoutes = [
            'laporan.index',
            'laporan.cetak',
            'laporan.export-excel',
            'logout',
        ];

        $currentRouteName = $request->route() ? $request->route()->getName() : null;

        if (in_array($currentRouteName, $allowedRoutes, true)) {
            return $next($request);
        }

        // Non-admin trying to access admin-only pages gets redirected to laporan
        return redirect()->route('laporan.index')->with('warning', 'Akses dibatasi. Halaman ini hanya untuk Administrator.');
    }
}
