<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('status_login') !== true) {
            return redirect()->route('login.form');
        }

        $adminId = (int) $request->session()->get('admin_id');
        if ($adminId <= 0 || !Admin::where('id_admin', $adminId)->exists()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login.form')->with('error', 'Sesi berakhir. Silakan login kembali.');
        }

        return $next($request);
    }
}
