<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\WalletService;
use App\Notifications\LoginOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'otpPending' => $request->session()->has('login_otp_user_id'),
        ]);
    }

    public function login(LoginRequest $request)
    {
        // Keep your existing credential validation and throttling.
        $request->authenticate();

        $user = Auth::user();
        $remember = $request->boolean('remember');

        // Do not leave the user authenticated before OTP verification.
        Auth::logout();

        $request->session()->regenerate();

        $this->sendLoginOtp($request, $user, $remember);

        return redirect()->route('login')
            ->with('status', 'A verification code has been sent to your registered email.');
    }

    private function sendLoginOtp(
        Request $request,
        User $user,
        bool $remember = false
    ): void {
        $otp = (string) random_int(100000, 999999);

        $request->session()->put([
            'login_otp_user_id' => $user->id,
            'login_otp_hash' => Hash::make($otp),
            'login_otp_expires_at' => now()->addMinutes(5)->timestamp,
            'login_otp_attempts' => 0,
            'login_otp_remember' => $remember,
        ]);

        Notification::route('mail', $user->email)
            ->notify(new LoginOtpNotification($otp));
    }

    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $userId = $request->session()->get('login_otp_user_id');

        if (!$userId) {
            throw ValidationException::withMessages([
                'otp' => 'Your verification session has expired. Please log in again.',
            ]);
        }

        $attempts = (int) $request->session()->get('login_otp_attempts', 0);

        if ($attempts >= 5) {
            $this->clearLoginOtp($request);

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please log in again.',
            ]);
        }

        $expiresAt = $request->session()->get('login_otp_expires_at');

        if (!$expiresAt || now()->timestamp >= $expiresAt) {
            $this->clearLoginOtp($request);

            throw ValidationException::withMessages([
                'otp' => 'Your OTP has expired. Please log in again.',
            ]);
        }

        $otpHash = $request->session()->get('login_otp_hash');

        if (!$otpHash || !Hash::check($request->otp, $otpHash)) {
            $request->session()->increment('login_otp_attempts');

            throw ValidationException::withMessages([
                'otp' => 'The OTP you entered is incorrect.',
            ]);
        }

        $user = User::find($userId);

        if (!$user) {
            $this->clearLoginOtp($request);

            throw ValidationException::withMessages([
                'otp' => 'Your account could not be found. Please log in again.',
            ]);
        }

        $remember = (bool) $request->session()->get(
            'login_otp_remember',
            false
        );

        $this->clearLoginOtp($request);

        Auth::login($user, $remember);

        $request->session()->regenerate();

        // Preserve your existing retailer wallet initialization.
        if ($user->role === 'retailer') {
            app(WalletService::class)->getWallet($user);
        }

        return redirect()->intended(
            $user->role === 'admin' ? '/admin' : '/retailer'
        );
    }

    public function resendLoginOtp(Request $request)
    {
        $userId = $request->session()->get('login_otp_user_id');

        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['otp' => 'Please log in again to request an OTP.']);
        }

        // Basic resend cooldown: one request every 60 seconds.
        $lastSent = $request->session()->get('login_otp_last_sent_at');

        if ($lastSent && now()->timestamp - $lastSent < 60) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait 60 seconds before requesting another OTP.',
            ]);
        }

        $user = User::find($userId);

        if (!$user) {
            $this->clearLoginOtp($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Please log in again.']);
        }

        $remember = (bool) $request->session()->get(
            'login_otp_remember',
            false
        );

        $this->sendLoginOtp($request, $user, $remember);

        $request->session()->put(
            'login_otp_last_sent_at',
            now()->timestamp
        );

        return redirect()->route('login')
            ->with('status', 'A new verification code has been sent.');
    }

    private function clearLoginOtp(Request $request): void
    {
        $request->session()->forget([
            'login_otp_user_id',
            'login_otp_hash',
            'login_otp_expires_at',
            'login_otp_attempts',
            'login_otp_remember',
            'login_otp_last_sent_at',
        ]);
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20|unique:users,phone',
            'shop_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'vat_number' => 'nullable|string|max:50',
            'company_reg_number' => 'nullable|string|max:50',
            'utr_number' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => 'retailer',
            'kyc_status' => 'pending',
            'is_active' => true,
        ]);

        // Assign role and create wallet
        $user->assignRole('retailer');
        $walletService = app(WalletService::class);
        $walletService->getWallet($user);

        event(new \Illuminate\Auth\Events\Registered($user));

        Auth::login($user);

        return redirect('/retailer/dashboard');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        return redirect('/login')->with('success', 'Password reset successful. Please login.');
    }
}
