<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Cek username + password (SoftDeletes otomatis exclude deleted_at != null)
        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $user = Auth::user();

        // Cek apakah user terdaftar di utility 'sesi'
        if (! $user->userUtility) {
            Auth::logout();
            return back()
                ->withErrors(['username' => 'Akun Anda tidak memiliki akses ke sistem ini.'])
                ->onlyInput('username');
        }

        // Cek is_blocked
        if ($user->isBlocked()) {
            Auth::logout();
            return back()
                ->withErrors(['username' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        $cabangCode = $user->getCabangId();
        $cabangName = $cabangCode
            ? \DB::connection('sqlsrv')->table('sesi_master_cabang')->where('Code', $cabangCode)->value('Name')
            : null;

        session([
            'cabang_code' => $cabangCode,
            'cabang_name' => $cabangName,
        ]);

        return redirect()->intended(
            $this->redirectByRole($user->userUtility->role)
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function redirectByRole(string $jabatan): string
    {
        return match ($jabatan) {
            'KG' => route('dashboard.kg'),
            'WM' => route('dashboard.wm'),
            'WH' => route('dashboard.wh'),
            'DCI'=> route('dashboard.dci'),
            'KA' => '/kaadmin/dashboard',
            default => route('dashboard'),
        };
    }
}