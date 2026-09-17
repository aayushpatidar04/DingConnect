<script setup>
import { Head } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
defineOptions({ layout: RetailerLayout });

const props = defineProps({
    stats: Object,
    recentTransactions: Array,
    chartData: Array,
});
</script>

<template>
    <Head title="Dashboard - MK Network" />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">Dashboard</h1>
            <p class="text-dark-300 mt-1">
                Welcome back, {{ $page.props.auth.user.name }}!
            </p>
        </div>

        <!-- Low Balance Warning -->
        <div
            v-if="stats.low_balance"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4"
        >
            <div class="flex items-center">
                <svg
                    class="w-5 h-5 text-red-400 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                    />
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-red-400">
                        Low Wallet Balance
                    </h3>
                    <p class="text-sm text-red-300 mt-1">
                        Your available balance (£
                        {{ stats.available_balance.toFixed(2) }}) is below the
                        minimum threshold.
                    </p>
                    <a
                        href="/retailer/wallet"
                        class="mt-2 inline-block text-sm font-medium text-red-400 underline hover:text-red-300"
                        >Top Up Now</a
                    >
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="stat-gradient-1 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">Wallet Balance</div>
                <div class="text-2xl font-bold text-white mt-1">
                    £ {{ stats.wallet_balance.toFixed(2) }}
                </div>
                <div class="text-xs text-blue-200 mt-1">
                    Available: £ {{ stats.available_balance.toFixed(2) }}
                </div>
            </div>
            <div class="stat-gradient-2 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Success Rate</div>
                <div class="text-2xl font-bold text-white mt-1">
                    {{ stats.success_rate }}%
                </div>
                <div class="text-xs text-green-200 mt-1">
                    {{ stats.total_transactions }} total
                </div>
            </div>
            <div class="stat-gradient-3 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">This Month</div>
                <div class="text-2xl font-bold text-white mt-1">
                    £ {{ stats.this_month_volume.toFixed(2) }}
                </div>
                <div class="text-xs text-blue-200 mt-1">
                    {{ stats.this_month_success }} successful
                </div>
            </div>
            <div class="stat-gradient-4 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Today</div>
                <div class="text-2xl font-bold text-white mt-1">
                    {{ stats.today_transactions }}
                </div>
                <div class="text-xs text-green-200 mt-1">transactions</div>
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
            <h3 class="text-lg font-semibold text-white mb-4">Last 7 Days</h3>
            <div class="grid grid-cols-7 gap-2">
                <div
                    v-for="day in chartData"
                    :key="day.date"
                    class="text-center"
                >
                    <div class="text-xs text-dark-300">
                        {{
                            new Date(day.date).toLocaleDateString("en-US", {
                                weekday: "short",
                            })
                        }}
                    </div>
                    <div
                        class="h-20 bg-primary/20 rounded-lg flex flex-col items-center justify-center mt-1"
                    >
                        <div class="text-sm font-semibold text-primary-light">
                            {{ day.count }}
                        </div>
                    </div>
                    <div class="text-xs text-dark-400 mt-1">
                        {{ day.count }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div
            class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden"
        >
            <div class="p-6 border-b border-dark-600">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">
                        Recent Transactions
                    </h3>
                    <a
                        href="/retailer/transactions"
                        class="text-sm text-primary-light hover:text-primary transition"
                        >View All</a
                    >
                </div>
            </div>
            <div class="divide-y divide-dark-600">
                <div
                    v-for="txn in recentTransactions.slice(0, 8)"
                    :key="txn.id"
                    class="p-4 flex items-center justify-between hover:bg-dark-700 transition"
                >
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-10 h-10 bg-dark-700 rounded-lg flex items-center justify-center text-sm font-semibold text-primary-light"
                        >
                            {{ txn.operator?.name?.charAt(0) || "?" }}
                        </div>
                        <div>
                            <div class="font-medium text-white">
                                {{ txn.mobile_number }}
                            </div>
                            <div class="text-xs text-dark-400">
                                {{ txn.operator?.name || "Unknown" }} ·
                                {{ txn.created_at }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium text-white">
                            £ {{ txn.amount.toFixed(2) }}
                        </div>
                        <span
                            :class="[
                                'px-2 py-0.5 text-xs rounded-full',
                                txn.status === 'success'
                                    ? 'bg-accent/20 text-accent-light'
                                    : txn.status === 'failed'
                                      ? 'bg-red-500/20 text-red-400'
                                      : 'bg-yellow-500/20 text-yellow-400',
                            ]"
                        >
                            {{ txn.status }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="!recentTransactions.length"
                    class="p-8 text-center text-dark-400"
                >
                    No transactions yet
                </div>
            </div>
        </div>
    </div>
</template>
