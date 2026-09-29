<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
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

        $query = Transaction::where('user_id', $user->id)->with(['operator', 'country']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where('mobile_number', 'like', "%{$search}%")
                ->orWhere('receipt_number', 'like', "%{$search}%");
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $transactions = $query->orderByDesc('created_at')->paginate(25);

        return Inertia::render('Retailer/Transactions/Index', compact('transactions'));
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
