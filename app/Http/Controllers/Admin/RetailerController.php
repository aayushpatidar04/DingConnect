<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RetailersExport;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class RetailerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only('search', 'status', 'kyc_status');
        $filters['status'] = $filters['status'] ?? 'active';

        $retailers = User::where('role', 'retailer')
            ->with(['wallet', 'retailerCommissionProfile.level'])
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();          // keeps filters in the pagination links

        return Inertia::render('Admin/Retailers/Index', compact('retailers', 'filters'));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new RetailersExport($request->only('search', 'status', 'kyc_status')),
            'retailers_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function show(Request $request, User $retailer)
    {
        $filters = $request->only(['search', 'type', 'from', 'to']);

        $showTransactions = ! isset($filters['type']) || $filters['type'] === 'debit';

        $transactions = collect();
        if ($showTransactions) {
            $transactions = Transaction::query()
                ->where('user_id', $retailer->id)
                ->with(['operator:id,name'])
                ->when($filters['search'] ?? null, function ($q, $s) {
                    $q->where(function ($q) use ($s) {
                        $q->where('mobile_number', 'like', "%{$s}%")
                            ->orWhere('receipt_number', 'like', "%{$s}%");
                    });
                })
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->orderByDesc('created_at')
                ->get();
        }

        $ledgers = WalletLedger::query()
            ->whereHas('wallet', fn ($q) => $q->where('user_id', $retailer->id))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where('description', 'like', "%{$s}%");
            })
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->get();

        $items = collect();

        // foreach ($transactions as $txn) {
        //     $items->push([
        //         'id' => 'txn_'.$txn->id,
        //         'source' => 'transaction',
        //         'type' => 'debit',
        //         'created_at' => $txn->created_at,
        //         'amount' => (float) $txn->amount,
        //         'balance_before' => null,
        //         'balance_after' => null,
        //         'reference' => $txn->receipt_number ?? '-',
        //         'mobile_number' => $txn->mobile_number,
        //         'operator' => $txn->operator?->name ?? '-',
        //         'status' => $txn->status,
        //         'has_view' => true,
        //         'view_url' => '/admin/transactions/'.$txn->id,
        //     ]);
        // }

        foreach ($ledgers as $ledger) {
            $items->push([
                'id' => 'ledger_'.$ledger->id,
                'source' => 'ledger',
                'type' => $ledger->type,
                'created_at' => $ledger->created_at,
                'amount' => (float) $ledger->amount,
                'balance_before' => $ledger->balance_before ? (float) $ledger->balance_before : null,
                'balance_after' => $ledger->balance_after ? (float) $ledger->balance_after : null,
                'reference' => $ledger->description ?? '-',
                'mobile_number' => null,
                'operator' => null,
                'status' => null,
                'has_view' => (bool) $ledger->transaction_id,
                'view_url' => $ledger->transaction_id ? '/admin/transactions/'.$ledger->transaction_id : null,
            ]);
        }

        $items = $items->sortByDesc('created_at')->values();

        $page = $request->input('page', 1);
        $perPage = 15;

        $activities = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $operators = Operator::with('country:id,iso_code')
            ->orderBy('name')
            ->get(['id', 'name', 'country_id']);

        $retailer->load(['wallet']);

        return Inertia::render('Admin/Retailers/Show', compact(
            'retailer', 'activities', 'operators', 'filters'
        ));
    }

    public function create()
    {
        return Inertia::render('Admin/Retailers/Create');
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

        Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
        $user->assignRole('retailer');

        // Create wallet
        $user->wallet()->create();

        return redirect()->route('admin.retailers.index')->with('success', 'Retailer created successfully!');
    }

    public function edit(User $retailer)
    {
        return Inertia::render('Admin/Retailers/Edit', compact('retailer'));
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
            return back()->with('error', 'Verification already processed.');
        }

        $retailer->update([
            'kyc_status' => 'approved',
            'kyc_verified_at' => now(),
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
        $retailer->assignRole('retailer');

        return back()->with('success', 'Retailer verification approved!');
    }

    public function block(Request $request, User $retailer)
    {
        $retailer->update(['is_active' => ! $retailer->is_active]);

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
        $amount = round((float) $request->amount, 2);
        $description = $request->description ?: 'Manual credit by admin';

        // Record the credit in the retailer's wallet top-ups list, and point the ledger entry at it
        // (same convention as Stripe top-ups).
        DB::transaction(function () use ($walletService, $retailer, $request, $amount, $description) {
            $entry = WalletTopup::create([
                'user_id' => $retailer->id,
                'amount' => $amount,
                'currency' => config('platform.wallet.currency', 'GBP'),
                'fee_amount' => 0,
                'fee_percentage' => 0,
                'total_charged' => $amount,
                'payment_method' => 'wallet',
                'payment_gateway' => WalletTopup::GATEWAY_ADMIN,
                'payment_response' => [
                    'source' => 'admin',
                    'description' => $description,
                    'credited_by' => $request->user()->id,
                ],
                'status' => 'completed',
            ]);

            $walletService->credit($retailer->id, $amount, null, $description);
        });

        return back()->with('success', "£ {$amount} credited to {$retailer->name}'s wallet!");
    }

    public function processKyc(Request $request, User $retailer)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        if ($validated['action'] === 'approve') {
            Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'web']);
            $retailer->assignRole('retailer');
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

        return back()->with('success', 'Verification '.$validated['action'].'ed successfully!');
    }

    public function toggleStatus(User $retailer)
    {
        $retailer->update([
            'is_active' => ! $retailer->is_active,
        ]);

        return back()->with(
            'success',
            $retailer->is_active
                ? 'Retailer activated successfully.'
                : 'Retailer deactivated successfully.'
        );
    }

    public function destroy(User $retailer)
    {
        $retailer->update(['is_active' => false]);

        return back()->with('success', 'Retailer deleted successfully!');
    }
}
