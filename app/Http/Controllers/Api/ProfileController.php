<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->profileData($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'shop_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'company_reg_number' => ['nullable', 'string', 'max:20'],
            'utr_number' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($validated['new_password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
            }

            $validated['password'] = $validated['new_password'];
        }

        unset($validated['current_password'], $validated['new_password']);
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $this->profileData($request),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $request->user()->update(['password' => $validated['password']]);
        $request->user()->tokens()->delete();

        return response()->json(['success' => true, 'message' => 'Password updated. Please sign in again.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(Request $request): array
    {
        return $request->user()->only([
            'id',
            'name',
            'email',
            'phone',
            'role',
            'shop_name',
            'address',
            'city',
            'county',
            'postcode',
            'vat_number',
            'company_reg_number',
            'utr_number',
            'kyc_status',
            'kyc_rejection_reason',
            'kyc_verified_at',
            'is_active',
        ]);
    }
}
