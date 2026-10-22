<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RetailerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->is_active || Auth::user()->role !== 'retailer') {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (Auth::check() && ! Auth::user()->is_active) {
                    return response()->json(['message' => 'Your account is deactivated.'], 403);
                }

                return response()->json(['message' => 'Unauthorized. Retailer access required.'], 403);
            }

            return redirect()->route('login')->with('error', 'Access denied. Retailer privileges required.');
        }

        return $next($request);
    }
}
