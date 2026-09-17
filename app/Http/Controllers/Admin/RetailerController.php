<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Models\Transaction;
use App\Services\WalletService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RetailersExport;
use Inertia\Inertia;

class RetailerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'retailer')
            ->with(['wallet', 'transactions']);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('shop_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->get('status')) {
            if ($status === 'active')
                $query->where('is_active', true);
            if ($status === 'inactive')
                $query->where('is_active', false);
            if ($status === 'kyc_pending')
                $query->where('kyc_status', 'pending');
        }


        $retailers = $query->orderByDesc('created_at')->paginate(25);

        return Inertia::render('Admin/Retailers/Index', compact('retailers'));
    }

    public function show(User $retailer)
    {
        $retailer->load(['wallet', 'transactions' => fn($q) => $q->latest()->limit(50)]);

        return Inertia::render('Admin/Retailers/Show', compact('retailer'));
    }

    public function create()
    {
        return Inertia::render("Admin/Retailers/Create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'shop_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
        ]);

        $user = User::create([
            ...$validated,
            'role' => 'retailer',
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(), // Auto-verify admin-created accounts
        ]);

        // Create wallet
        $user->wallet()->create();

        return redirect()->route('admin.retailers.index')->with('success', 'Retailer created successfully!');
    }

    public function edit(User $retailer)
    {
        return Inertia::render("Admin/Retailers/Edit", compact("retailer"));
    }

    public function update(Request $request, User $retailer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($retailer->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($retailer->id)],
            'shop_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'vat_number' => 'nullable|string|max:20',
            'company_reg_number' => 'nullable|string|max:20',
            'utr_number' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $retailer->update($validated);

        return back()->with('success', 'Retailer updated successfully!');
    }

    public function approve(Request $request, User $retailer)
    {
        if ($retailer->kyc_status !== 'pending') {
            return back()->with('error', 'KYC already processed.');
        }

        $retailer->update([
            'kyc_status' => 'approved',
            'kyc_verified_at' => now(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Retailer KYC approved!');
    }

    public function block(Request $request, User $retailer)
    {
        $retailer->update(['is_active' => !$retailer->is_active]);

        return back()->with('success', $retailer->is_active ? 'Retailer activated!' : 'Retailer deactivated!');
    }

    public function creditWallet(Request $request, User $retailer)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ]);

        $walletService = app(WalletService::class);
        $wallet = $walletService->getWallet($retailer);
        $walletService->credit($wallet, $request->amount, 'admin_credit', null, $request->description ?? 'Manual credit by admin');

        return back()->with('success', "£ {$request->amount} credited to {$retailer->name}'s wallet!");
    }

    public function processKyc(Request $request, User $retailer)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        if ($validated['action'] === 'approve') {
            $retailer->update([
                'kyc_status' => 'approved',
                'kyc_verified_at' => now(),
                'is_active' => true,
            ]);
        } else {
            $retailer->update([
                'kyc_status' => 'rejected',
                'kyc_rejection_reason' => $validated['rejection_reason'],
            ]);
        }

        return back()->with('success', 'KYC ' . $validated['action'] . 'ed successfully!');
    }

    public function export(Request $request)
    {
        return Excel::download(new RetailersExport, 'retailers_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function destroy(User $retailer)
    {
        $retailer->update(['is_active' => false]);

        return back()->with('success', 'Retailer deleted successfully!');
    }
}
