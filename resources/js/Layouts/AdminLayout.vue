<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Echo from "laravel-echo";
import MkLogo from "@/Components/MkLogo.vue";
import Toast from "@/Components/Toast.vue";
import { useToast, toasts } from "@/composables/useToast.js";

const showMobileMenu = ref(false);
const notifications = ref([]);
let echoChannel = null;

const user = usePage().props.auth.user;

onMounted(() => {
    if (user) {
        echoChannel = Echo.private(`admin.${user.id}`);
        echoChannel.listen(".NewTransaction", (e) => {
            notifications.value.unshift(e);
        });
    }
});

onUnmounted(() => {
    if (echoChannel) echoChannel.stopListening();
});
</script>

<template>
    <div class="min-h-screen bg-dark-900">
        <Head>
            <title>
                {{ $page.component?.props?.pageTitle || "Admin Panel" }} - MK
                Network
            </title>
        </Head>

        <div class="flex h-screen">
            <!-- Sidebar -->
            <aside
                :class="[
                    'bg-dark-800 border-r border-dark-600 transition-all duration-300 flex flex-col',
                    showMobileMenu ? 'w-64' : 'w-20',
                ]"
            >
                <!-- Logo -->
                <div
                    class="h-16 flex items-center justify-center border-b border-dark-600 px-3"
                >
                    <Link href="/admin" class="flex items-center">
                        <MkLogo v-if="showMobileMenu" size="xl" />
                        <img v-else :src="'/favicon.png'" class="h-12 w-auto" />
                    </Link>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 py-4 space-y-1 px-3 overflow-y-auto">
                    <Link
                        href="/admin"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url === '/admin' ||
                            $page.url.startsWith('/admin/dashboard')
                                ? 'bg-primary text-white shadow-lg shadow-primary/20'
                                : 'text-dark-300 hover:bg-dark-700 hover:text-white'
                        "
                    >
                        <svg
                            class="w-5 h-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 12l2-2m7-7l7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Dashboard</span
                        >
                    </Link>

                    <Link
                        href="/admin/retailers"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/retailers')
                                ? 'bg-primary text-white shadow-lg shadow-primary/20'
                                : 'text-dark-300 hover:bg-dark-700 hover:text-white'
                        "
                    >
                        <svg
                            class="w-5 h-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Retailers</span
                        >
                    </Link>

                    <Link
                        href="/admin/transactions"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/transactions')
                                ? 'bg-primary text-white shadow-lg shadow-primary/20'
                                : 'text-dark-300 hover:bg-dark-700 hover:text-white'
                        "
                    >
                        <svg
                            class="w-5 h-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Transactions</span
                        >
                    </Link>

                    <Link
                        href="/admin/operators"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/operators')
                                ? 'bg-primary text-white shadow-lg shadow-primary/20'
                                : 'text-dark-300 hover:bg-dark-700 hover:text-white'
                        "
                    >
                        <svg
                            class="w-5 h-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Operators</span
                        >
                    </Link>

                    <Link
                        href="/admin/settings"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/settings')
                                ? 'bg-primary text-white shadow-lg shadow-primary/20'
                                : 'text-dark-300 hover:bg-dark-700 hover:text-white'
                        "
                    >
                        <svg
                            class="w-5 h-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Settings</span
                        >
                    </Link>
                </nav>

                <!-- User Section -->
                <div class="p-3 border-t border-dark-600">
                    <div class="flex items-center px-3 py-2">
                        <div
                            class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                        >
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div v-if="showMobileMenu" class="ml-3">
                            <p class="text-sm font-medium text-white">
                                {{ user.name }}
                            </p>
                            <p class="text-xs text-dark-400">Administrator</p>
                        </div>
                    </div>
                    <form
                        v-if="showMobileMenu"
                        method="POST"
                        action="/logout"
                        class="mt-2"
                    >
                        <button
                            type="submit"
                            class="w-full text-left px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-dark-700 rounded-lg transition"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Header -->
                <header
                    class="h-16 bg-dark-800 border-b border-dark-600 flex items-center justify-between px-6"
                >
                    <button
                        @click="showMobileMenu = !showMobileMenu"
                        class="p-2 text-dark-300 hover:text-white hover:bg-dark-700 rounded-lg transition"
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

                    <h1 class="text-lg font-semibold text-white">
                        {{ $page.props.pageTitle || "Dashboard" }}
                    </h1>

                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div v-if="notifications.length > 0" class="relative">
                            <button
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
                                    class="absolute -top-1 -right-1 bg-accent text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"
                                >
                                    {{ notifications.length }}
                                </span>
                            </button>
                        </div>

                        <div class="text-sm text-dark-300">{{ user.name }}</div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto bg-dark-900 p-6">
                    <slot />
                    <Toast />
                </main>
            </div>
        </div>
    </div>
</template>
