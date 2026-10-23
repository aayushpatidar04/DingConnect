<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Services\WalletService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                ...$request->safe()->except(['password_confirmation']),
                'role' => 'retailer',
                'password' => $request->validated('password'),
                'kyc_status' => 'pending',
                'is_active' => true,
            ]);

            Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
            $user->assignRole('retailer');
            app(WalletService::class)->getWallet($user);

            return $user;
        });

        $token = $user->createToken('mkallnetwork');

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your account is pending Verification approval.',
            'data' => [
                'user' => $this->userData($user),
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Account is deactivated.'], 403);
        }

        $this->sendOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'A verification code has been sent to your registered email.',
            'data' => ['requires_otp' => true, 'email' => $user->email],
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->login_otp_hash || ! $user->login_otp_expires_at) {
            throw ValidationException::withMessages(['otp' => 'Your verification session has expired. Please log in again.']);
        }

        if ($user->login_otp_attempts >= 5 || $user->login_otp_expires_at->isPast()) {
            $this->clearOtp($user);
            throw ValidationException::withMessages(['otp' => 'Your verification code has expired. Please log in again.']);
        }

        if (! $user->is_active) {
            $this->clearOtp($user);

            return response()->json(['success' => false, 'message' => 'Account is deactivated.'], 403);
        }

        if (! Hash::check($validated['otp'], $user->login_otp_hash)) {
            $user->increment('login_otp_attempts');
            throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
        }

        $this->clearOtp($user);
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->role === 'retailer') {
            app(WalletService::class)->getWallet($user);
        }
        $token = $user->createToken('mkallnetwork');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->userData($user),
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->login_otp_hash) {
            throw ValidationException::withMessages(['email' => 'Please log in again to request a verification code.']);
        }

        if ($user->login_otp_last_sent_at && $user->login_otp_last_sent_at->addSeconds(60)->isFuture()) {
            throw ValidationException::withMessages(['email' => 'Please wait before requesting another verification code.']);
        }

        $this->sendOtp($user);

        return response()->json(['success' => true, 'message' => 'A new verification code has been sent.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => __($status === Password::RESET_LINK_SENT ? $status : Password::RESET_LINK_SENT),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['success' => true, 'message' => __($status)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['wallet', 'roles']);

        return response()->json(['success' => true, 'data' => $this->userData($user)]);
    }

    private function sendOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'login_otp_hash' => Hash::make($otp),
            'login_otp_expires_at' => now()->addMinutes(5),
            'login_otp_attempts' => 0,
            'login_otp_last_sent_at' => now(),
        ])->save();

        Notification::route('mail', $user->email)->notify(new LoginOtpNotification($otp));
    }

    private function clearOtp(User $user): void
    {
        $user->forceFill([
            'login_otp_hash' => null,
            'login_otp_expires_at' => null,
            'login_otp_attempts' => 0,
            'login_otp_last_sent_at' => null,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'roles' => $user->getRoleNames(),
            'shop_name' => $user->shop_name,
            'kyc_status' => $user->kyc_status,
            'is_active' => $user->is_active,
            'wallet' => $user->wallet,
        ];
    }
}
