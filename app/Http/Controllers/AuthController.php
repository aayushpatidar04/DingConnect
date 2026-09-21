<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->role === 'retailer') {
            $walletService = app(WalletService::class);
            $walletService->getWallet($user);
        }

        return redirect()->intended($user->role === 'admin' ? '/admin' : '/retailer');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function register()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'phone'                 => 'required|string|max:20|unique:users,phone',
            'shop_name'             => 'nullable|string|max:255',
            'address'               => 'nullable|string',
            'city'                  => 'nullable|string|max:100',
            'county'                => 'nullable|string|max:100',
            'postcode'              => 'nullable|string|max:20',
        ]);

        $user = User::create([
            ...$validated,
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => 'retailer',
            'kyc_status' => 'pending',
        ]);

        // Create wallet
        $walletService = app(WalletService::class);
        $walletService->getWallet($user);

        Auth::login($user);

        return redirect('/retailer/dashboard');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        return back()->with('status', 'If an account with that email exists, a password reset link will be sent.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        return redirect('/login')->with('status', 'Password reset successful. Please login.');
    }
}
