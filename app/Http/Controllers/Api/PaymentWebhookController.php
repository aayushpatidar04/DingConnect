<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('Stripe-Signature');
        $rawBody = $request->getContent();

        Log::info('Stripe webhook received', [
            'type' => $payload['type'] ?? 'unknown',
            'sig' => substr($signature ?? '', 0, 30),
            'has_raw_body' => !empty($rawBody),
            'raw_body_preview' => substr($rawBody, 0, 200),
        ]);

        if (empty($signature)) {
            Log::warning('Stripe webhook has NO signature header');
        }

        $topup = $this->paymentService->processWebhook($payload);

        if ($topup) {
            broadcast(new \App\Events\WalletCredited(
                $topup->user,
                (float) $topup->amount,
                $topup->user->wallet->balance
            ));

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'ignored'], 200);
    }
}
