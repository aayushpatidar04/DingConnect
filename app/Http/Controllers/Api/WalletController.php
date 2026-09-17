<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Http\Requests\WalletTopUpRequest;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(
        private WalletService        $wallet,
        private PaymentGatewayService $payment,
    ) {}

    public function balance(): JsonResponse
    {
        $balance = $this->wallet->getBalance(Auth::id());
        return response()->json(['success' => true, 'data' => ['balance' => $balance]]);
    }

    public function ledger(): JsonResponse
    {
        $ledger = DB::table('wallet_ledgers')
            ->where('wallet_id', Auth::user()->wallet?->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $ledger]);
    }

    public function topUp(WalletTopUpRequest $request): JsonResponse
    {
        $amount = (float) $request->amount;
        $user = Auth::user();

        $feePercentage = (float) config('platform.wallet_load_fee_percentage', 0);
        $feeFlat = (float) config('platform.wallet_load_fee_flat', 0);
        $fee = round(($amount * $feePercentage / 100) + $feeFlat, 2);
        $total = $amount + $fee;

        $gatewayOrderId = 'WLT-' . strtoupper(Str::random(12));

        DB::beginTransaction();
        try {
            $walletTopup = DB::table('wallet_topups')->insertGetId([
                'user_id'              => $user->id,
                'amount'               => $amount,
                'currency'             => 'GBP',
                'fee_amount'           => $fee,
                'fee_percentage'       => $feePercentage,
                'total_charged'        => $total,
                'payment_method'       => 'card',
                'payment_gateway'      => config('payment.gateway', 'razorpay'),
                'gateway_order_id'     => $gatewayOrderId,
                'status'               => 'pending',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $paymentResponse = $this->payment->createOrder($gatewayOrderId, $total, 'GBP');

            DB::table('wallet_topups')->where('id', $walletTopup)->update([
                'gateway_transaction_id' => $paymentResponse['id'],
                'payment_response'       => json_encode($paymentResponse),
            ]);

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Payment order created',
                'data'     => [
                    'topup_id'    => $walletTopup,
                    'gateway_order_id' => $gatewayOrderId,
                    'payment_gateway'  => config('payment.gateway', 'razorpay'),
                    'payment'         => $paymentResponse,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to initiate top-up: ' . $e->getMessage()], 500);
        }
    }
}
