<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Transaction;
use App\Services\DingConnectService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;


class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->only('search', 'status', 'type', 'operator_id', 'from', 'to');

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['operator:id,name', 'country:id,name'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                // grouped, so the user_id condition always applies
                $q->where(function ($q) use ($search) {
                    $q->where('mobile_number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, function ($q, $type) {
                if ($type === 'Immediate') {
                    // the page displays a null redemption_type as "Immediate"
                    $q->where(fn($q) => $q->where('redemption_type', 'Immediate')
                        ->orWhereNull('redemption_type'));
                } else {
                    $q->where('redemption_type', $type);
                }
            })
            ->when($filters['operator_id'] ?? null, fn($q, $v) => $q->where('operator_id', $v))
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString(); // keeps filters in the pagination links

        // only operators this retailer has actually used
        $operators = Operator::whereIn(
            'id',
            Transaction::where('user_id', $user->id)->whereNotNull('operator_id')->select('operator_id')
        )->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Retailer/Transactions/Index', [
            'transactions' => $transactions,
            'operators' => $operators,
            'filters' => $filters,
        ]);
    }

    public function show(Transaction $transaction, DingConnectService $dingService)
    {
        $this->authorize('view', $transaction);

        // Backfill description for old transactions that predate the job backfill
        if (
            $transaction->status === 'success' &&
            empty($transaction->description_markdown) &&
            empty($transaction->readmore_markdown)
        ) {
            try {
                $result = $dingService->getProductDescriptions([$transaction->sku_code]);
                $item = $result['data']['Items'][0] ?? null;

                if ($item) {
                    $transaction->update([
                        'description_markdown' => $item['DescriptionMarkdown'] ?? null,
                        'readmore_markdown' => $item['ReadMoreMarkdown'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Description backfill failed for transaction ' . $transaction->id . ': ' . $e->getMessage());
            }
        }

        $transaction->load(['operator', 'country']);

        return Inertia::render('Retailer/Transactions/Show', compact('transaction'));
    }

    public function receipt(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        $pdf = Pdf::loadView('pdf.receipt', compact('transaction'));

        return $pdf->download("receipt_{$transaction->receipt_number}.pdf");
    }

    public function poll(Transaction $transaction)
    {
        $this->authorize('view', $transaction);
        $transaction->load(['operator', 'country']);

        return response()->json(['transaction' => $transaction]);
    }
}
