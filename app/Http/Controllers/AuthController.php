<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, false)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        if (!Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your account is inactive. Contact administrator.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        app(ActivityLogService::class)->log(
            module: 'auth',
            action: 'login',
            entityType: User::class,
            entityId: (int) Auth::id(),
            description: 'User logged in.'
        );

        return redirect()->intended($this->defaultPathForRole((string) Auth::user()->role));
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();

        app(ActivityLogService::class)->log(
            module: 'auth',
            action: 'logout',
            entityType: User::class,
            entityId: $userId ? (int) $userId : null,
            description: 'User logged out.'
        );

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function defaultPathForRole(string $role): string
    {
        if ($role === User::ROLE_OWNER) {
            return route('accounts.dashboard');
        }

        if ($role === User::ROLE_PURCHASE) {
            return route('purchases.index');
        }

        return route('orders.index');
    }
}
