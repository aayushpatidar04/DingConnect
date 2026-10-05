<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && strtolower((string) $user->kyc_status) === 'approved') {
            return $next($request);
        }

        $message = match (strtolower((string) $user?->kyc_status)) {
            'rejected' => 'Your KYC was rejected. Please contact support.',
            'pending' => 'Your KYC is still under review. You can log in once it is approved.',
            default => 'Your KYC is not approved. Please contact support.',
        };

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The recharge/* endpoints are likely called via fetch/axios, so return JSON for those
        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        // Flash after invalidate(), otherwise the new session would not contain it
        return redirect('/')->with('error', $message);
    }
}