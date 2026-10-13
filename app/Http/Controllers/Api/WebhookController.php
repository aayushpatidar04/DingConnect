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
        $body = $request->getContent();

        try {
            $payload = json_decode($body, true);
            ProcessDingWebhook::dispatch($payload);
            return response()->json(['message' => 'OK']);
        } catch (\Throwable $e) {
            Log::error('DingConnect webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error processing webhook'], 500);
        }
    }
}
