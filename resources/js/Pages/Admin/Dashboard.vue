<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: Object,
    topRetailers: Array,
    recentTransactions: Array,
    chartData: Array,
});
</script>

<template>
    <Head title="Dashboard - Admin" />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">Dashboard</h1>
            <p class="text-dark-300">Welcome to the MK Network Admin Panel</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="stat-gradient-1 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">Total Retailers</div>
                <div class="text-3xl font-bold text-white mt-1">
                    {{ stats.total_retailers }}
                </div>
                <div class="text-xs text-blue-200 mt-1">
                    {{ stats.active_retailers }} active
                </div>
            </div>
            <div class="stat-gradient-2 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Today's Transactions</div>
                <div class="text-3xl font-bold text-white mt-1">
                    {{ stats.today_transactions }}
                </div>
                <div class="text-xs text-green-200 mt-1">
                    {{ stats.today_success }} success
                </div>
            </div>
            <div class="stat-gradient-3 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">Today's Volume</div>
                <div class="text-3xl font-bold text-white mt-1">
                    £ {{ Number(stats.today_volume).toFixed(2) }}
                </div>
                <div class="text-xs text-blue-200 mt-1">Recharge volume</div>
            </div>
            <div class="stat-gradient-4 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Success Rate</div>
                <div class="text-3xl font-bold text-white mt-1">
                    {{ stats.success_rate }}%
                </div>
                <div class="text-xs text-green-200 mt-1">All time</div>
            </div>
        </div>

        <!-- DingConnect Balance -->
        <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">
                        DingConnect Balance
                    </h3>
                    <p class="text-dark-300 text-sm mt-1">
                        Your wholesale balance with DingConnect
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-primary-light">
                        {{
                            stats.ding_balance?.success
                                ? stats.ding_balance.balance +
                                  " " +
                                  stats.ding_balance.currency
                                : "N/A"
                        }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Retailers & Recent Transactions -->
        <div class="grid lg:grid-cols-2 gap-6">
            <div
                class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden"
            >
                <div class="p-6 border-b border-dark-600">
                    <h3 class="text-lg font-semibold text-white">
                        Top Retailers This Month
                    </h3>
                </div>
                <div class="divide-y divide-dark-600">
                    <div
                        v-for="retailer in topRetailers"
                        :key="retailer.id"
                        class="p-4 flex items-center justify-between hover:bg-dark-700 transition"
                    >
                        <div>
                            <div class="font-medium text-white">
                                {{ retailer.shop_name || retailer.name }}
                            </div>
                            <div class="text-sm text-dark-400">
                                {{ retailer.phone }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-primary-light">
                                {{ retailer.month_transactions }}
                            </div>
                            <div class="text-xs text-dark-400">
                                transactions
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="topRetailers.length === 0"
                        class="p-6 text-center text-dark-400"
                    >
                        No data yet
                    </div>
                </div>
            </div>

            <div
                class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden"
            >
                <div
                    class="p-6 border-b border-dark-600 flex items-center justify-between"
                >
                    <h3 class="text-lg font-semibold text-white">
                        Recent Transactions
                    </h3>
                    <Link
                        href="/admin/transactions"
                        class="text-sm text-primary-light hover:text-primary transition"
                        >View All</Link
                    >
                </div>
                <div class="divide-y divide-dark-600">
                    <div
                        v-for="txn in recentTransactions.slice(0, 8)"
                        :key="txn.id"
                        class="p-4 flex items-center justify-between hover:bg-dark-700 transition"
                    >
                        <div class="flex items-center space-x-3">
                            <div
                                class="w-8 h-8 bg-dark-700 rounded-lg flex items-center justify-center text-sm font-semibold text-primary-light"
                            >
                                {{ txn.operator?.name?.charAt(0) || "?" }}
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white">
                                    {{ txn.mobile_number }}
                                </div>
                                <div class="text-xs text-dark-400">
                                    {{ txn.user?.name || "Unknown" }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-white">
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
                </div>
            </div>
        </div>
    </div>
</template>
