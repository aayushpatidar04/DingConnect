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
const showAdminDropdown = ref(false);

const user = usePage().props.auth.user;

onMounted(() => {
    if (user && typeof Echo !== "undefined" && Echo.private) {
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
    <div class="min-h-screen bg-surface-0">
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
                    'bg-surface-2 border-r border-surface-3 transition-all duration-300 flex flex-col',
                    showMobileMenu ? 'w-64' : 'w-20',
                ]"
            >
                <!-- Logo -->
                <div
                    class="h-16 flex items-center justify-center border-b border-surface-3 px-3"
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
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                        href="/admin/allowed-numbers"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/allowed-numbers')
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                        <span v-if="showMobileMenu" class="ml-3 font-medium"
                            >Allowed Numbers</span
                        >
                    </Link>

                    <Link
                        href="/admin/settings"
                        class="flex items-center px-3 py-3 rounded-xl transition-all duration-200"
                        :class="
                            $page.url.includes('/settings')
                                ? 'bg-primary text-ink-900 shadow-lg shadow-primary/20'
                                : 'text-ink-500 hover:bg-surface-2 hover:text-ink-900'
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
                <div class="p-3 border-t border-surface-3">
                    <div
                        class="flex items-center gap-2"
                        :class="showMobileMenu ? '' : 'flex-col'"
                    >
                        <div v-if="showMobileMenu"
                            class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-ink-900 font-bold text-sm flex-shrink-0"
                        >
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>

                        <div v-if="showMobileMenu" class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-ink-900 truncate">
                                {{ user.name }}
                            </p>
                            <p class="text-xs text-ink-500">Administrator</p>
                        </div>

                        <Link
                            method="post"
                            href="/logout"
                            as="button"
                            title="Logout"
                            class="p-2 rounded-lg text-red-700 hover:text-red-600 hover:bg-surface-2 transition flex-shrink-0"
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
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                        </Link>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Header -->
                <header
                    class="h-16 bg-surface-2 border-b border-surface-3 flex items-center justify-between px-6"
                >
                    <button
                        @click="showMobileMenu = !showMobileMenu"
                        class="p-2 text-ink-500 hover:text-ink-900 hover:bg-surface-2 rounded-lg transition"
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

                    <h1 class="text-lg font-semibold text-ink-900">
                        {{ $page.props.pageTitle || "Dashboard" }}
                    </h1>

                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div v-if="notifications.length > 0" class="relative">
                            <button
                                class="p-2 text-ink-500 hover:text-ink-900 hover:bg-surface-2 rounded-lg transition relative"
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
                                    class="absolute -top-1 -right-1 bg-accent text-ink-900 text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"
                                >
                                    {{ notifications.length }}
                                </span>
                            </button>
                        </div>

                        <div class="text-sm text-ink-500">{{ user.name }}</div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto bg-surface-0 p-6">
                    <slot />
                    <Toast />
                </main>
            </div>
        </div>
    </div>
</template>
