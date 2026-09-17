<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class PaymentGatewayService
{
    protected string $gateway;

    public function __construct()
    {
        $this->gateway = config('payment.gateway', 'razorpay');
    }

    /**
     * Create a payment order with the active gateway.
     */
    public function createOrder(string $orderId, float $amount, string $currency = 'GBP'): array
    {
        return match ($this->gateway) {
            'razorpay' => $this->createRazorpayOrder($orderId, $amount, $currency),
            'stripe'   => $this->createStripeOrder($orderId, $amount, $currency),
            default    => throw new \RuntimeException("Gateway not supported: {$this->gateway}"),
        };
    }

    /**
     * Verify a payment via the gateway.
     */
    public function verifyPayment(string $orderId, string $paymentId): bool
    {
        return match ($this->gateway) {
            'razorpay' => $this->verifyRazorpayPayment($orderId, $paymentId),
            'stripe'   => $this->verifyStripePayment($orderId, $paymentId),
            default    => false,
        };
    }

    /**
     * Handle webhook callback from the gateway.
     */
    public function handleWebhook(array $payload): void
    {
        match ($this->gateway) {
            'razorpay' => $this->handleRazorpayWebhook($payload),
            'stripe'   => $this->handleStripeWebhook($payload),
            default    => Log::warning("Unknown gateway in webhook: {$this->gateway}"),
        };
    }

    protected function createRazorpayOrder(string $orderId, float $amount, string $currency): array
    {
        $api = new Api(config('payment.razorpay.key'), config('payment.razorpay.secret'));

        $order = $api->order->create([
            'amount'         => (int) round($amount * 100), // Convert to paise
            'currency'       => $currency,
            'receipt'        => $orderId,
            'payment_capture' => 1,
        ]);

        return [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'currency'         => $order['currency'],
            'status'           => $order['status'],
            'receipt'          => $order['receipt'],
            'key'              => config('payment.razorpay.key'),
            'name'             => config('app.name'),
            'description'      => 'Wallet Top-Up',
            'prefill'          => [
                'name'  => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'theme'            => ['color' => '#0d9488'],
        ];
    }

    protected function verifyRazorpayPayment(string $orderId, string $paymentId): bool
    {
        try {
            $api     = new Api(config('payment.razorpay.key'), config('payment.razorpay.secret'));
            $payment = $api->payment->fetch($paymentId);

            return $payment['order_id'] === $orderId && $payment['status'] === 'captured';
        } catch (\Throwable $e) {
            Log::error('Razorpay payment verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function handleRazorpayWebhook(array $payload): void
    {
        $eventType = $payload['event'] ?? '';
        $paymentData = $payload['payload']['payment']['entity'] ?? [];

        if (in_array($eventType, ['payment.captured', 'payment.failed']) && isset($paymentData['order_id'])) {
            $orderId = $paymentData['order_id'];

            \DB::table('wallet_topups')
                ->where('gateway_order_id', $orderId)
                ->where('status', 'pending')
                ->update([
                    'status' => $eventType === 'payment.captured' ? 'completed' : 'failed',
                    'updated_at' => now(),
                ]);

            if ($eventType === 'payment.captured') {
                $topup = \DB::table('wallet_topups')->where('gateway_order_id', $orderId)->first();
                if ($topup) {
                    \DB::table('wallets')->where('user_id', $topup->user_id)->increment('balance', $topup->amount);

                    \DB::table('wallet_ledgers')->insert([
                        'wallet_id'      => $topup->user_id,
                        'type'           => 'credit',
                        'amount'         => $topup->amount,
                        'description'    => "Wallet loaded via Razorpay — {$orderId}",
                        'reference_type' => 'WalletTopup',
                        'reference_id'   => $topup->id,
                        'created_at'     => now(),
                    ]);
                }
            }
        }
    }

    protected function createStripeOrder(string $orderId, float $amount, string $currency): array
    {
        \Stripe\Stripe::setApiKey(config('payment.stripe.secret'));

        $intent = \Stripe\PaymentIntent::create([
            'amount'         => (int) round($amount * 100),
            'currency'       => strtolower($currency),
            'description'    => 'Wallet Top-Up',
            'metadata'       => ['order_id' => $orderId],
        ]);

        return [
            'id'            => $intent->id,
            'client_secret' => $intent->client_secret,
            'amount'        => $intent->amount,
            'currency'      => $intent->currency,
            'status'        => $intent->status,
            'publishable_key' => config('payment.stripe.publishable'),
        ];
    }

    protected function verifyStripePayment(string $orderId, string $paymentId): bool
    {
        try {
            \Stripe\Stripe::setApiKey(config('payment.stripe.secret'));
            $intent = \Stripe\PaymentIntent::retrieve($paymentId);

            return $intent->metadata->order_id === $orderId && $intent->status === 'succeeded';
        } catch (\Throwable $e) {
            Log::error('Stripe payment verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function handleStripeWebhook(array $payload): void
    {
        $eventType = $payload['type'] ?? '';
        $intentData = $payload['data']['object'] ?? [];

        if ($eventType === 'payment_intent.succeeded' && isset($intentData['metadata']['order_id'])) {
            $orderId = $intentData['metadata']['order_id'];

            \DB::table('wallet_topups')
                ->where('gateway_order_id', $orderId)
                ->where('status', 'pending')
                ->update([
                    'gateway_transaction_id' => $intentData['id'],
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

            $topup = \DB::table('wallet_topups')->where('gateway_order_id', $orderId)->first();
            if ($topup) {
                \DB::table('wallets')->where('user_id', $topup->user_id)->increment('balance', $topup->amount);

                \DB::table('wallet_ledgers')->insert([
                    'wallet_id'      => $topup->user_id,
                    'type'           => 'credit',
                    'amount'         => $topup->amount,
                    'description'    => "Wallet loaded via Stripe — {$orderId}",
                    'reference_type' => 'WalletTopup',
                    'reference_id'   => $topup->id,
                    'created_at'     => now(),
                ]);
            }
        } elseif ($eventType === 'payment_intent.payment_failed') {
            $orderId = $intentData['metadata']['order_id'] ?? null;
            if ($orderId) {
                \DB::table('wallet_topups')
                    ->where('gateway_order_id', $orderId)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed', 'updated_at' => now()]);
            }
        }
    }
}
