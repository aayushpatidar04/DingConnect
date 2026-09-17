<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        return Inertia::render('Landing/Index', [
            'features' => [
                [
                    'icon' => 'zap',
                    'title' => 'Instant Recharges',
                    'description' => 'Process mobile top-ups in seconds across all major operators and countries.',
                ],
                [
                    'icon' => 'shield',
                    'title' => 'Secure & Reliable',
                    'description' => 'Bank-grade security with real-time transaction tracking and instant notifications.',
                ],
                [
                    'icon' => 'wallet',
                    'title' => 'Wallet Management',
                    'description' => 'Prepaid wallet system with multiple top-up options. No minimum balance required.',
                ],
                [
                    'icon' => 'bar-chart',
                    'title' => 'Business Insights',
                    'description' => 'Track your recharges, earnings, and performance with detailed analytics.',
                ],
            ],
            'stats' => [
                'retailers' => '500+',
                'transactions' => '1L+',
                'operators' => '200+',
                'countries' => '50+',
            ],
        ]);
    }
}
