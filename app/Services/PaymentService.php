<?php

namespace App\Services;

use App\Models\WalletTopup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class PaymentService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('platform.payment.stripe.secret_key'));
    }

    /**
     * Create a Stripe Payment Intent for wallet top-up
     */
    public function createPaymentIntent(float $amount, string $currency = 'GBP', ?int $userId = null, ?string $customerEmail = null): array
    {
        try {
            $params = [
                'amount' => (int) round($amount * 100), // Stripe uses cents/pence
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'user_id' => $userId ?? 'guest',
                    'purpose' => 'wallet_topup',
                ],
            ];

            if ($customerEmail) {
                $params['receipt_email'] = $customerEmail;
            }

            $intent = $this->stripe->paymentIntents->create($params);

            return [
                'success' => true,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
                'status' => $intent->status,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::error('Payment Intent Creation Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Confirm a payment intent (for client-side confirmation)
     */
    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'success' => true,
                'status' => $intent->status,
                'payment_intent' => $intent,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe Confirm Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Stripe webhook
     */
    public function processWebhook(array $payload): ?WalletTopup
    {
        $type = $payload['type'] ?? null;
        $dataObject = $payload['data']['object'] ?? [];

        if ($type === 'payment_intent.succeeded') {
            $paymentIntentId = $dataObject['id'] ?? null;

            if (!$paymentIntentId) return null;

            $topup = WalletTopup::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($topup && $topup->status !== 'completed') {
                $topup->update([
                    'status' => 'completed',
                    'payment_response' => $payload,
                ]);

                // Credit the wallet
                $walletService = app(WalletService::class);
                $walletService->credit(
                    $topup->user_id,
                    $topup->amount,
                    $topup->id,
                    "Wallet top-up via Stripe"
                );

                return $topup->fresh();
            }
        }

        if ($type === 'payment_intent.payment_failed') {
            $paymentIntentId = $dataObject['id'] ?? null;

            if (!$paymentIntentId) return null;

            $topup = WalletTopup::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($topup) {
                $topup->update([
                    'status' => 'failed',
                    'payment_response' => $payload,
                ]);

                return $topup->fresh();
            }
        }

        return null;
    }

    /**
     * Refund a payment intent
     */
    public function refundPayment(string $paymentIntentId, ?float $amount = null): array
    {
        try {
            $params = ['payment_intent' => $paymentIntentId];

            if ($amount) {
                $params['amount'] = (int) round($amount * 100);
            }

            $refund = $this->stripe->refunds->create($params);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe Refund Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get payment intent details
     */
    public function getPaymentIntent(string $paymentIntentId): array
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'success' => true,
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
            ];

        } catch (ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
