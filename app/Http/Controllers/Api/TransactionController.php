<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpRequest;
use App\Services\DingConnectService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(
        private DingConnectService $ding,
        private WalletService    $wallet
    ) {}

    public function topUp(TopUpRequest $request): JsonResponse
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            // Hold funds from retailer wallet
            $this->wallet->hold($user->id, $request->amount, 'Mobile top-up');

            $dingResult = $this->ding->sendTopUp($request->only(['countryIso', 'operatorCode', 'mobileNumber', 'amount']));

            $transaction = DB::table('transactions')->insertGetId([
                'user_id'              => $user->id,
                'mobile_number'        => $request->mobileNumber,
                'operator_id'          => $request->operator_id,
                'country_id'           => $request->country_id,
                'amount'               => $request->amount,
                'currency'             => 'GBP',
                'ding_transaction_id'  => $dingResult['dingTransactionId'] ?? null,
                'ding_order_reference' => $dingResult['orderReference'] ?? null,
                'ding_response'        => json_encode($dingResult),
                'status'               => $dingResult['success'] ? 'processing' : 'failed',
                'failure_reason'       => $dingResult['failureReason'] ?? null,
                'product_type'         => $request->product_type ?? 'mobile_topup',
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            if (!$dingResult['success']) {
                $this->wallet->refund($user->id, $request->amount, "Top-up failed: {$dingResult['failureReason']}", $transaction);
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => $dingResult['failureReason'] ?? 'Transaction failed',
                    'data'    => ['transaction_id' => $transaction],
                ], 422);
            }

            $this->wallet->debit($user->id, $request->amount, "Mobile top-up to {$request->mobileNumber}", $transaction);

            DB::commit();

            broadcast(new \App\Events\TransactionCreated($transaction, $user));

            return response()->json([
                'success' => true,
                'message' => 'Recharge initiated successfully',
                'data'    => ['transaction_id' => $transaction],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    public function history(): JsonResponse
    {
        $user = Auth::user();
        $transactions = DB::table('transactions')
            ->join('operators', 'transactions.operator_id', '=', 'operators.id')
            ->join('countries', 'transactions.country_id', '=', 'countries.id')
            ->where('transactions.user_id', $user->id)
            ->select(
                'transactions.*',
                'operators.name as operator_name',
                'countries.name as country_name',
                'countries.flag_emoji'
            )
            ->orderByDesc('transactions.created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    public function show(int $id): JsonResponse
    {
        $transaction = DB::table('transactions')
            ->join('operators', 'transactions.operator_id', '=', 'operators.id')
            ->join('countries', 'transactions.country_id', '=', 'countries.id')
            ->where('transactions.id', $id)
            ->where('transactions.user_id', Auth::id())
            ->select('transactions.*', 'operators.name as operator_name', 'countries.name as country_name', 'countries.flag_emoji')
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function status(int $id): JsonResponse
    {
        $transaction = DB::table('transactions')->where('id', $id)->where('user_id', Auth::id())->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'processing') {
            $status = $this->ding->checkStatus($transaction->ding_transaction_id);
            if ($status['status'] !== 'processing') {
                DB::table('transactions')->where('id', $id)->update([
                    'status' => $status['status'] === 'success' ? 'success' : 'failed',
                    'failure_reason' => $status['failureReason'] ?? null,
                    'callback_received' => true,
                    'callback_received_at' => now(),
                    'updated_at' => now(),
                ]);
                $transaction = DB::table('transactions')->where('id', $id)->first();
            }
        }

        return response()->json(['success' => true, 'data' => $transaction]);
    }
}
