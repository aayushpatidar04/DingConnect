<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:50'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $transactions = Transaction::whereBelongsTo($request->user())
            ->with(['operator:id,name', 'country:id,name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('mobile_number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, function ($query, string $type): void {
                if ($type === 'Immediate') {
                    $query->where(fn ($query) => $query->where('redemption_type', 'Immediate')->orWhereNull('redemption_type'));
                } else {
                    $query->where('redemption_type', $type);
                }
            })
            ->when($filters['operator_id'] ?? null, fn ($query, $operatorId) => $query->where('operator_id', $operatorId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::whereBelongsTo($request->user())
            ->with(['operator', 'country'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::whereBelongsTo($request->user())->findOrFail($id);

        return response()->json(['success' => true, 'data' => $transaction]);
    }
}
