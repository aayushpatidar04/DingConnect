<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Echo from "laravel-echo";
import MkLogo from "@/Components/MkLogo.vue";
import Toast from "@/Components/Toast.vue";
import { useToast, toasts } from "@/composables/useToast.js";

const showMobileMenu = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
let echoChannel = null;

const user = usePage().props.auth.user;

onMounted(() => {
    if (user && typeof Echo !== "undefined" && Echo.private) {
        echoChannel = Echo.private(`retailer.${user.id}`);

        echoChannel.listen(".RechargeSuccess", (e) => {
            notifications.value.unshift({
                type: "success",
                title: "Recharge Successful",
                message: e.message,
                time: "Just now",
            });
            unreadCount.value++;
        });

        echoChannel.listen(".RechargeFailed", (e) => {
            notifications.value.unshift({
                type: "error",
                title: "Recharge Failed",
                message: e.message,
                time: "Just now",
            });
            unreadCount.value++;
        });

        echoChannel.listen(".WalletCredited", (e) => {
            notifications.value.unshift({
                type: "success",
                title: "Wallet Credited",
                message: e.message,
                time: "Just now",
            });
            unreadCount.value++;
        });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening();
    }
});

function logout() {
    if (echoChannel && typeof Echo !== "undefined" && Echo.leave) {
        Echo.leave(`retailer.${user.id}`);
    }
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "/logout";
    document.body.appendChild(form);
    form.submit();
}
</script>

<template>
    <div class="min-h-screen bg-dark-900">
        <Head>
            <title>
                {{ $page.component?.props?.pageTitle || "Dashboard" }} - MK
                Network
            </title>
        </Head>

        <!-- Top Navigation -->
        <nav class="bg-dark-800 border-b border-dark-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Left: Logo & Mobile Menu -->
                    <div class="flex items-center">
                        <button
                            @click="showMobileMenu = !showMobileMenu"
                            class="sm:hidden p-2 text-dark-300 hover:text-white"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                        <Link
                            href="/retailer"
                            class="flex items-center ml-2 sm:ml-0"
                        >
                            <MkLogo size="xl" />
                        </Link>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="hidden sm:flex sm:items-center sm:space-x-1">
                        <Link
                            href="/retailer"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url.startsWith('/retailer') &&
                                !$page.url.includes('recharge') &&
                                !$page.url.includes('wallet') &&
                                !$page.url.includes('transactions')
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Dashboard</Link
                        >
                        <Link
                            href="/retailer/recharge"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url.includes('/recharge')
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Recharge</Link
                        >
                        <Link
                            href="/retailer/transactions"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url.includes('/transactions')
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Transactions</Link
                        >
                        <Link
                            href="/retailer/wallet"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="
                                $page.url.includes('/wallet')
                                    ? 'bg-dark-700 text-white'
                                    : 'text-dark-300 hover:text-white hover:bg-dark-700'
                            "
                            >Wallet</Link
                        >
                    </div>

                    <!-- Right: Notifications & Profile -->
                    <div class="flex items-center space-x-3">
                        <!-- Notifications -->
                        <div class="relative">
                            <button
                                @click="toggleNotifications"
                                class="p-2 text-dark-300 hover:text-white hover:bg-dark-700 rounded-lg transition relative"
                            >
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                </svg>
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -top-1 -right-1 bg-accent text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"
                                >
                                    {{ unreadCount > 9 ? "9+" : unreadCount }}
                                </span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div
                                v-if="notifications.length > 0"
                                class="absolute right-0 mt-2 w-80 bg-dark-800 rounded-xl shadow-xl border border-dark-600 z-50"
                            >
                                <div class="p-3 border-b border-dark-600">
                                    <h3 class="font-semibold text-white">
                                        Notifications
                                    </h3>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <div
                                        v-for="notif in notifications.slice(
                                            0,
                                            5,
                                        )"
                                        :key="notif.time"
                                        class="p-3 border-b border-dark-700 last:border-0 hover:bg-dark-700 transition"
                                    >
                                        <div class="flex items-start">
                                            <div class="flex-1">
                                                <p
                                                    class="text-sm font-medium text-white"
                                                >
                                                    {{ notif.title }}
                                                </p>
                                                <p
                                                    class="text-sm text-dark-300 mt-1"
                                                >
                                                    {{ notif.message }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile -->
                        <button
                            class="flex items-center space-x-2 p-2 hover:bg-dark-700 rounded-lg transition"
                        >
                            <div
                                class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm"
                            >
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                            <span
                                class="hidden md:block text-sm font-medium text-white"
                                >{{ user.name }}</span
                            >
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div
                v-if="showMobileMenu"
                class="sm:hidden border-t border-dark-600 bg-dark-800"
            >
                <div class="pt-2 pb-3 space-y-1 px-4">
                    <Link
                        href="/retailer"
                        class="block px-3 py-2 rounded-lg text-base font-medium text-dark-300 hover:text-white hover:bg-dark-700"
                        >Dashboard</Link
                    >
                    <Link
                        href="/retailer/recharge"
                        class="block px-3 py-2 rounded-lg text-base font-medium text-dark-300 hover:text-white hover:bg-dark-700"
                        >Recharge</Link
                    >
                    <Link
                        href="/retailer/transactions"
                        class="block px-3 py-2 rounded-lg text-base font-medium text-dark-300 hover:text-white hover:bg-dark-700"
                        >Transactions</Link
                    >
                    <Link
                        href="/retailer/wallet"
                        class="block px-3 py-2 rounded-lg text-base font-medium text-dark-300 hover:text-white hover:bg-dark-700"
                        >Wallet</Link
                    >
                    <Link
                        href="/retailer/profile"
                        class="block px-3 py-2 rounded-lg text-base font-medium text-dark-300 hover:text-white hover:bg-dark-700"
                        >Profile</Link
                    >
                    <button
                        @click="logout"
                        class="block w-full text-left px-3 py-2 rounded-lg text-base font-medium text-red-400 hover:text-red-300 hover:bg-dark-700"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
            <Toast />
        </main>

        <!-- Footer -->
        <footer class="bg-dark-800 border-t border-dark-600 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <p class="text-center text-sm text-dark-400">
                    MK Network Communications. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>
