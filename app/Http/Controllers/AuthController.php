<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('status_login') === true) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        try {
            $admin = Admin::where('username', $credentials['username'])->first();

            if (!$admin) {
                return back()->withInput()->with('error', 'Username atau password salah.');
            }

            $storedPassword = (string) $admin->password;
            $isValidPassword = Hash::check($credentials['password'], $storedPassword);

            // Migrasi sekali: password lama yang belum di-hash (hanya jika bukan format bcrypt).
            if (!$isValidPassword && !$this->isBcryptHash($storedPassword)) {
                $isValidPassword = hash_equals($storedPassword, $credentials['password']);
            }

            if (!$isValidPassword) {
                return back()->withInput()->with('error', 'Username atau password salah.');
            }

            if (!$this->isBcryptHash($storedPassword)) {
                $admin->update([
                    'password' => Hash::make($credentials['password']),
                ]);
            }

            $request->session()->regenerate();
            $request->session()->put([
                'status_login' => true,
                'admin_id' => $admin->id_admin,
                'admin_username' => $admin->username,
                'admin_nama' => $admin->nama_lengkap,
                'admin_role' => $admin->peran,
            ]);

            return redirect()->route('dashboard');
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database connection failed during login: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Koneksi ke server database terputus. Pastikan service database sudah berjalan.');
        } catch (\Exception $e) {
            \Log::error('System error during login: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan sistem saat memproses login.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.form')->with('success', 'Anda berhasil logout.');
    }

    private function isBcryptHash(string $password): bool
    {
        return preg_match('/^\$2[ayb]\$.{56}$/', $password) === 1;
    }
}
