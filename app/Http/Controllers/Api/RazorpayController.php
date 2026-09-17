<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Razorpay\Api\Order as RazorpayOrder;
use Razorpay\Api\Payment as RazorpayPayment;
use Razorpay\Api\Refund as RazorpayRefund;

class RazorpayController extends Controller
{
    /**
     * Create a Razorpay order for wallet top-up.
     *
     * @param  array  $params
     * @return array
     */
    public function createOrder(array $params): array
    {
        $razorpayKey = config('payment.razorpay.key');
        $apiSecret   = config('payment.razorpay.secret');

        if (!$razorpayKey || !$apiSecret) {
            throw new \RuntimeException('Razorpay credentials not configured');
        }

        $api = new \Razorpay\Api\Api($razorpayKey, $apiSecret);

        $order = $api->order->create([
            'amount'         => (int) round($params['amount'] * 100), // paise
            'currency'       => $params['currency'] ?? 'GBP',
            'receipt'        => $params['receipt'] ?? 'ORD-' . strtoupper(Str::random(8)),
            'payment_capture' => 1,
            'notes'          => [
                'platform' => config('app.name'),
                'user_id'  => Auth::id(),
                'type'     => 'wallet_topup',
            ],
        ]);

        return [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'currency'         => $order['currency'],
            'status'           => $order['status'],
            'receipt'          => $order['receipt'],
            'key'              => $razorpayKey,
            'name'             => config('app.name'),
            'description'      => 'Wallet Top-Up',
            'prefill'          => [
                'name'  => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
            'theme'            => [
                'color' => '#0d9488',
            ],
        ];
    }

    /**
     * Verify Razorpay webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('payment.razorpay.webhook_secret');

        if (!$secret) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify payment using Razorpay API.
     */
    public function verifyPayment(string $razorpayOrderId, string $razorpayPaymentId): bool
    {
        try {
            $api = $this->getApiInstance();
            $payment = $api->payment->fetch($razorpayPaymentId);

            return $payment['order_id'] === $razorpayOrderId
                && $payment['status'] === 'captured';
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Verify Razorpay order exists and is authorized.
     */
    public function verifyOrderId(string $razorpayOrderId): bool
    {
        try {
            $api    = $this->getApiInstance();
            $order  = $api->order->fetch($razorpayOrderId);

            return $order['status'] === 'created'
                || $order['status'] === 'authorized';
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Process payment verification and confirm wallet top-up.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
        ]);

        $orderId   = $request->input('razorpay_order_id');
        $paymentId = $request->input('razorpay_payment_id');

        if (!$this->verifyPayment($orderId, $paymentId)) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Please contact support.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $topup = DB::table('wallet_topups')
                ->where('gateway_order_id', $orderId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$topup) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            if (Auth::id() !== $topup->user_id) {
                abort(403);
            }

            DB::table('wallet_topups')->where('id', $topup->id)->update([
                'stripe_payment_intent_id' => $paymentId,
                'gateway_transaction_id'   => $paymentId,
                'status'                   => 'completed',
                'updated_at'               => now(),
            ]);

            DB::table('wallets')->where('user_id', $topup->user_id)->increment('balance', $topup->amount);

            DB::table('wallet_ledgers')->insert([
                'wallet_id'     => $topup->user_id,
                'transaction_id' => $topup->id,
                'type'          => 'credit',
                'amount'        => $topup->amount,
                'balance_before'=> 0,
                'balance_after' => $topup->amount,
                'reference_type'=> 'WalletTopup',
                'reference_id'  => $topup->id,
                'description'   => "Wallet loaded via Razorpay — Order {$orderId}",
                'created_at'    => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet loaded successfully',
                'data'    => ['topup_id' => $topup->id, 'new_balance' => $topup->amount],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Razorpay webhook events.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (!$this->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        $eventType = $event['event'] ?? '';

        DB::beginTransaction();
        try {
            switch ($eventType) {
                case 'payment.captured':
                    $this->handlePaymentCaptured($event['payload']['payment']['entity']);
                    break;
                case 'payment.failed':
                    $this->handlePaymentFailed($event['payload']['payment']['entity']);
                    break;
                case 'refund.processed':
                    $this->handleRefundProcessed($event['payload']['refund']['entity']);
                    break;
                case 'order.paid':
                    $this->handleOrderPaid($event['payload']['order']['entity']);
                    break;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
        }

        return response()->json(['message' => 'OK']);
    }

    private function handlePaymentCaptured(array $payment): void
    {
        $orderId = $payment['order_id'] ?? null;

        if (!$orderId) {
            return;
        }

        $topup = DB::table('wallet_topups')
            ->where('gateway_order_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if (!$topup) {
            return;
        }

        DB::table('wallet_topups')->where('id', $topup->id)->update([
            'status'     => 'completed',
            'updated_at' => now(),
        ]);

        DB::table('wallets')->where('user_id', $topup->user_id)->increment('balance', $topup->amount);
    }

    private function handlePaymentFailed(array $payment): void
    {
        $orderId = $payment['order_id'] ?? null;

        if (!$orderId) {
            return;
        }

        DB::table('wallet_topups')
            ->where('gateway_order_id', $orderId)
            ->where('status', 'pending')
            ->update(['status' => 'failed', 'updated_at' => now()]);
    }

    private function handleRefundProcessed(array $refund): void
    {
        $paymentId = $refund['payment_id'] ?? null;

        if (!$paymentId) {
            return;
        }

        DB::table('wallet_topups')
            ->where('stripe_payment_intent_id', $paymentId)
            ->where('status', 'completed')
            ->update(['status' => 'refunded', 'updated_at' => now()]);
    }

    private function handleOrderPaid(array $order): void
    {
        $orderId = $order['id'] ?? null;

        if (!$orderId) {
            return;
        }

        DB::table('wallet_topups')
            ->where('gateway_order_id', $orderId)
            ->where('status', 'pending')
            ->update(['status' => 'processing', 'updated_at' => now()]);
    }

    private function getApiInstance(): \Razorpay\Api\Api
    {
        return new \Razorpay\Api\Api(
            config('payment.razorpay.key'),
            config('payment.razorpay.secret')
        );
    }
}
