<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDingWebhook;
use App\Services\DingConnectService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    public function __construct(
        private DingConnectService $ding,
        private WalletService      $wallet,
    ) {}

    public function dingCallback(Request $request): JsonResponse
    {
        $signature = $request->header('X-Ding-Signature', '');
        $body = $request->getContent();

        $expected = hash_hmac('sha256', $body, config('ding.webhook_secret', ''));
        if (!hash_equals($expected, $signature)) {
            Log::warning('DingConnect webhook signature mismatch');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        try {
            $payload = json_decode($body, true);
            ProcessDingWebhook::dispatch($payload);
            return response()->json(['message' => 'OK']);
        } catch (\Throwable $e) {
            Log::error('DingConnect webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error processing webhook'], 500);
        }
    }

    public function razorpayCallback(Request $request, RazorpayController $razorpay): JsonResponse
    {
        return $razorpay->handleWebhook($request);
    }
}
