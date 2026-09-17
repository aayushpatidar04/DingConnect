<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import MkLogo from "@/Components/MkLogo.vue";
defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <Head title="MK Network - Mobile Top-Up Platform" />

        <!-- Hero Section -->
        <section class="relative bg-dark-900 overflow-hidden">
            <!-- Background Effects -->
            <div class="absolute inset-0">
                <div
                    class="absolute top-0 right-0 w-96 h-96 bg-primary/20 rounded-full blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 left-0 w-96 h-96 bg-accent/10 rounded-full blur-3xl"
                ></div>
            </div>

            <div
                class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-32"
            >
                <div class="text-center">
                    <!-- Logo with animation -->
                    <div class="flex justify-center mb-8 animate-float">
                        <MkLogo size="xl" />
                    </div>

                    <h1
                        class="text-5xl sm:text-7xl font-bold tracking-tight mb-6 animate-fade-in-up"
                    >
                        <span class="gradient-text">Mobile Top-Up</span><br />
                        <span class="text-white">Distribution Platform</span>
                    </h1>

                    <p
                        class="text-xl text-dark-300 max-w-3xl mx-auto mb-10 animate-fade-in-up stagger-2"
                    >
                        The complete B2B solution for retailers. Fund your
                        wallet, process recharges instantly, and grow your
                        business with MK Network.
                    </p>

                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-up stagger-3"
                    >
                        <Link
                            href="/login"
                            class="btn-primary px-8 py-4 bg-primary text-white rounded-xl font-semibold text-lg text-center"
                        >
                            Retailer Login
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-20 bg-dark-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div
                        class="stat-gradient-1 rounded-2xl p-6 text-center card-hover"
                    >
                        <div class="text-4xl font-bold text-white mb-2">
                            500+
                        </div>
                        <div class="text-blue-100 text-sm">
                            Active Retailers
                        </div>
                    </div>
                    <div
                        class="stat-gradient-2 rounded-2xl p-6 text-center card-hover"
                    >
                        <div class="text-4xl font-bold text-white mb-2">
                            1L+
                        </div>
                        <div class="text-green-100 text-sm">Transactions</div>
                    </div>
                    <div
                        class="stat-gradient-3 rounded-2xl p-6 text-center card-hover"
                    >
                        <div class="text-4xl font-bold text-white mb-2">
                            200+
                        </div>
                        <div class="text-blue-100 text-sm">Operators</div>
                    </div>
                    <div
                        class="stat-gradient-4 rounded-2xl p-6 text-center card-hover"
                    >
                        <div class="text-4xl font-bold text-white mb-2">
                            50+
                        </div>
                        <div class="text-green-100 text-sm">Countries</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-dark-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-white mb-4">
                        Why Choose MK Network?
                    </h2>
                    <p class="text-lg text-dark-300">
                        Everything you need to run a successful mobile recharge
                        business
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600 card-hover"
                    >
                        <div
                            class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center mb-4"
                        >
                            <svg
                                class="w-6 h-6 text-primary-light"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Instant Recharges
                        </h3>
                        <p class="text-dark-300">
                            Process mobile top-ups in seconds across all major
                            operators and countries.
                        </p>
                    </div>

                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600 card-hover"
                    >
                        <div
                            class="w-12 h-12 bg-accent/20 rounded-xl flex items-center justify-center mb-4"
                        >
                            <svg
                                class="w-6 h-6 text-accent-light"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Secure & Reliable
                        </h3>
                        <p class="text-dark-300">
                            Bank-grade security with real-time transaction
                            tracking and instant notifications.
                        </p>
                    </div>

                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600 card-hover"
                    >
                        <div
                            class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center mb-4"
                        >
                            <svg
                                class="w-6 h-6 text-primary-light"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4v-4m14 4v-4M5 10h.01M12 10h.01M19 10h.01"
                                />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Wallet Management
                        </h3>
                        <p class="text-dark-300">
                            Prepaid wallet system with multiple top-up options.
                            No minimum balance required.
                        </p>
                    </div>

                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600 card-hover"
                    >
                        <div
                            class="w-12 h-12 bg-accent/20 rounded-xl flex items-center justify-center mb-4"
                        >
                            <svg
                                class="w-6 h-6 text-accent-light"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Business Insights
                        </h3>
                        <p class="text-dark-300">
                            Track your recharges, earnings, and performance with
                            detailed analytics.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="py-20 bg-dark-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-white mb-4">
                        How It Works
                    </h2>
                    <p class="text-lg text-dark-300">
                        Get started in 3 simple steps
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div
                            class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4"
                        >
                            1
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Register & Verify
                        </h3>
                        <p class="text-dark-300">
                            Sign up as a retailer and get your KYC verified in
                            24 hours.
                        </p>
                    </div>
                    <div class="text-center">
                        <div
                            class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4"
                        >
                            2
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Fund Your Wallet
                        </h3>
                        <p class="text-dark-300">
                            Load your prepaid wallet via UPI, cards, or bank
                            transfer.
                        </p>
                    </div>
                    <div class="text-center">
                        <div
                            class="w-16 h-16 bg-accent text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4"
                        >
                            3
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">
                            Start Recharging
                        </h3>
                        <p class="text-dark-300">
                            Process instant recharges for any operator at
                            transparent pricing.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-dark-900 relative overflow-hidden">
            <div class="absolute inset-0">
                <div
                    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-primary/20 rounded-full blur-3xl"
                ></div>
            </div>
            <div
                class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10"
            >
                <h2 class="text-4xl font-bold text-white mb-4">
                    Ready to Start Your Recharge Business?
                </h2>
                <p class="text-xl text-dark-300 mb-8">
                    Contact us to become a partner retailer.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        href="/contact"
                        class="btn-primary px-8 py-4 bg-primary text-white rounded-xl font-semibold text-lg text-center"
                    >
                        Contact Us
                    </Link>
                    <Link
                        href="/login"
                        class="px-8 py-4 border-2 border-white text-white rounded-xl font-semibold text-lg hover:bg-white/10 transition text-center"
                    >
                        Retailer Login
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>
