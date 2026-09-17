<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DingCallback;
use App\Models\Transaction;
use App\Services\DingConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DingConnectController extends Controller
{
    public function __construct(protected DingConnectService $dingService)
    {
    }

    public function callback(Request $request)
    {
        Log::info('DingConnect callback received', $request->all());

        try {
            $transaction = $this->dingService->processCallback($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Callback processed successfully',
                'transaction_id' => $transaction->id,
            ], 200);

        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid DingConnect callback: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('DingConnect callback processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
        }
    }

    public function checkStatus(Request $request, Transaction $transaction)
    {
        $result = $this->dingService->checkStatus($transaction->ding_transaction_id);

        return response()->json($result);
    }
}
