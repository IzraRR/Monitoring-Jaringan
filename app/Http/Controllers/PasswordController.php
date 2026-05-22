<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function index(): View
    {
        return view('password.index');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password_lama' => ['required', 'string', 'max:255'],
            'password_baru' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [], [
            'password_lama' => 'password lama',
            'password_baru' => 'password baru',
            'password_baru_confirmation' => 'konfirmasi password',
        ]);

        $adminId = (int) $request->session()->get('admin_id');
        if ($adminId <= 0) {
            return redirect()->route('login.form')->with('error', 'Sesi admin tidak valid, silakan login ulang.');
        }

        $admin = Admin::find($adminId);
        if (!$admin) {
            $request->session()->invalidate();

            return redirect()->route('login.form')->with('error', 'Akun admin tidak ditemukan.');
        }

        if (!Hash::check($validated['password_lama'], $admin->password)) {
            return back()->withErrors(['password_lama' => 'Password lama tidak sesuai.'])->withInput();
        }

        $admin->update([
            'password' => Hash::make($validated['password_baru']),
        ]);

        return redirect()->route('password.index')->with('success', 'Password berhasil diperbarui.');
    }
}
