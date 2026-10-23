<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RetailerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'kyc_status' => ['nullable', 'in:pending,approved,rejected'],
        ]);
        $retailers = User::where('role', 'retailer')
            ->with('wallet')
            ->filter($filters)
            ->latest()
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $retailers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'shop_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'company_reg_number' => ['nullable', 'string', 'max:50'],
            'utr_number' => ['nullable', 'string', 'max:20'],
        ]);
        $retailer = User::create([
            ...$validated,
            'role' => 'retailer',
            'is_active' => true,
            'kyc_status' => 'pending',
            'email_verified_at' => now(),
        ]);
        Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
        $retailer->assignRole('retailer');
        app(WalletService::class)->getWallet($retailer);

        return response()->json(['success' => true, 'data' => $retailer->load('wallet')], 201);
    }

    public function show(User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $retailer->load([
            'wallet',
            'transactions' => fn ($query) => $query->with('operator')->latest()->limit(5),
        ]);

        return response()->json(['success' => true, 'data' => $retailer]);
    }

    public function update(Request $request, User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($retailer->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($retailer->id)],
            'shop_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'company_reg_number' => ['nullable', 'string', 'max:50'],
            'utr_number' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $retailer->update($validated);

        if (array_key_exists('is_active', $validated) && ! $retailer->is_active) {
            $retailer->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $retailer->fresh()]);
    }

    public function destroy(User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $retailer->update(['is_active' => false]);
        $retailer->tokens()->delete();

        return response()->json(['success' => true, 'message' => 'Retailer deactivated.']);
    }

    public function approve(User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);

        if ($retailer->kyc_status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Verification has already been processed.'], 409);
        }

        $retailer->update(['kyc_status' => 'approved', 'kyc_verified_at' => now(), 'is_active' => true]);
        Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
        $retailer->assignRole('retailer');

        return response()->json(['success' => true, 'data' => $retailer->fresh()]);
    }

    public function block(User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $retailer->update(['is_active' => ! $retailer->is_active]);

        if (! $retailer->is_active) {
            $retailer->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $retailer->fresh()]);
    }

    public function creditWallet(Request $request, User $retailer, WalletService $walletService): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $walletService->getWallet($retailer);
        $walletService->credit(
            $retailer->id,
            (float) $validated['amount'],
            null,
            $validated['description'] ?? 'Manual credit by admin',
        );

        return response()->json(['success' => true, 'data' => $retailer->fresh()->load('wallet')]);
    }

    public function processKyc(Request $request, User $retailer): JsonResponse
    {
        $retailer = $this->retailer($retailer);
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
        ]);
        $attributes = $validated['action'] === 'approve'
            ? ['kyc_status' => 'approved', 'kyc_verified_at' => now(), 'is_active' => true]
            : ['kyc_status' => 'rejected', 'kyc_rejection_reason' => $validated['rejection_reason']];
        $retailer->update($attributes);

        if ($validated['action'] === 'approve') {
            Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
            $retailer->assignRole('retailer');
        } else {
            $retailer->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $retailer->fresh()]);
    }

    private function retailer(User $user): User
    {
        abort_unless($user->role === 'retailer', 404);

        return $user;
    }
}
