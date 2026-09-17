<script setup>
import { Head, Link } from "@inertiajs/vue3";
import MkLogo from "@/Components/MkLogo.vue";
import Toast from "@/Components/Toast.vue";
import { useToast, toasts } from "@/composables/useToast.js";
</script>

<template>
    <div class="min-h-screen flex flex-col bg-dark-900">
        <Head>
            <title>
                {{ $page.component?.props?.pageTitle || "MK Network" }}
            </title>
        </Head>

        <!-- Top Navigation -->
        <header
            class="bg-dark-800/80 backdrop-blur-md border-b border-dark-600 sticky top-0 z-50"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center">
                        <MkLogo size="xl" />
                    </Link>

                    <!-- Desktop Nav -->
                    <nav class="hidden md:flex items-center space-x-1">
                        <Link
                            href="/"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url === '/'
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Home</Link
                        >
                        <Link
                            href="/contact"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url === '/contact'
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Contact</Link
                        >
                    </nav>

                    <!-- Right side -->
                    <div class="flex items-center space-x-3">
                        <Link
                            href="/login"
                            class="text-sm font-medium text-dark-300 hover:text-white transition"
                            >Sign In</Link
                        >
                        <Link
                            href="/register"
                            class="btn-primary px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium"
                            >Become a Retailer</Link
                        >
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1">
            <slot />
            <Toast />
        </main>

        <!-- Footer -->
        <footer class="bg-dark-800 border-t border-dark-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div class="md:col-span-2">
                        <Link href="/" class="inline-block mb-4">
                            <MkLogo size="xl" />
                        </Link>
                        <p class="text-dark-400 text-sm max-w-md mt-3">
                            The complete B2B mobile top-up distribution
                            platform. Fund your wallet, process recharges
                            instantly, and grow your business.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white mb-3">Contact</h3>
                        <div class="space-y-2">
                            <a
                                href="mailto:support@mknetwork.com"
                                class="block text-sm text-dark-400 hover:text-white transition"
                                >support@mknetwork.com</a
                            >
                            <span class="block text-sm text-dark-400"
                                >+91 96915 65883</span
                            >
                            <Link
                                href="/contact"
                                class="block text-sm text-primary-light hover:text-primary transition"
                                >Send Message</Link
                            >
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white mb-3">Legal</h3>
                        <div class="space-y-2">
                            <Link
                                href="#"
                                class="block text-sm text-dark-400 hover:text-white transition"
                                >Terms of Service</Link
                            >
                            <Link
                                href="#"
                                class="block text-sm text-dark-400 hover:text-white transition"
                                >Privacy Policy</Link
                            >
                            <Link
                                href="#"
                                class="block text-sm text-dark-400 hover:text-white transition"
                                >Refund Policy</Link
                            >
                        </div>
                    </div>
                </div>
                <div
                    class="border-t border-dark-600 mt-8 pt-6 flex flex-col md:flex-row items-center justify-between"
                >
                    <p class="text-dark-400 text-sm">
                        MK Network Communications. All rights reserved.
                    </p>
                    <p class="text-dark-500 text-xs mt-2 md:mt-0">
                        Designed by InTouch Software Solutions.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
