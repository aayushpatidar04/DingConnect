<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = DB::table('users')->insertGetId([
                'name'                  => $request->name,
                'email'                 => $request->email,
                'password'              => Hash::make($request->password),
                'phone'                 => $request->phone,
                'role'                  => 'retailer',
                'shop_name'             => $request->shop_name,
                'address'               => $request->address,
                'city'                  => $request->city,
                'county'                => $request->county,
                'postcode'              => $request->postcode,
                'vat_number'            => $request->vat_number,
                'company_reg_number'    => $request->company_reg_number,
                'kyc_status'            => 'pending',
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // Create wallet
            DB::table('wallets')->insert([
                'user_id'   => $user,
                'balance'   => 0,
                'currency'  => 'GBP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign default role
            DB::table('model_has_roles')->insert([
                'role_id'   => 2,
                'model_type' => 'App\\Models\\User',
                'model_id'  => $user,
            ]);

            DB::commit();

            $token = DB::table('personal_access_tokens')->insertGetId([
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id'   => $user,
                'name'           => 'auth_token',
                'token'          => hash('sha256', $request->email . now() . random_bytes(16)),
                'abilities'      => json_encode(['*']),
                'last_used_at'   => null,
                'expires_at'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data'    => ['user_id' => $user, 'token' => $token],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['success' => false, 'message' => 'Account is deactivated'], 403);
        }

        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);

        $tokenResult = $user->createToken('auth_token');
        $plainTextToken = $tokenResult->accessToken->token;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'        => $user,
                'accessToken' => $plainTextToken,
                'token_type'  => 'Bearer',
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::user()->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user()->load('wallet', 'roles');
        return response()->json(['success' => true, 'data' => $user]);
    }
}
