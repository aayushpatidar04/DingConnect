<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: Object,
    topRetailers: Array,
    recentTransactions: Array,
    chartData: Array,
});

const maxRevenue = computed(() =>
    Math.max(...props.chartData.map((p) => p.revenue), 1),
);

const totalRevenue = computed(() =>
    props.chartData.reduce((sum, p) => sum + p.revenue, 0),
);

const formatCurrency = (v) =>
    new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: "GBP",
        maximumFractionDigits: 0,
    }).format(v);

const formatCompact = (v) =>
    new Intl.NumberFormat("en-IN", {
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(v);

const formatDate = (d) =>
    new Date(d).toLocaleDateString("en-GB", {
        timeZone: "Europe/London",
        day: "2-digit",
        month: "short",
    });

const barHeight = (value) =>
    Math.max(2, (value / maxRevenue.value) * 100) + "%";

// show a label roughly every 5th bar so 30 days don't overlap
const showLabel = (index) =>
    index % 5 === 0 || index === props.chartData.length - 1;
</script>

<template>
    <Head title="Dashboard - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Dashboard</h1>
            <p class="text-ink-500">Welcome to the MK Network Admin Panel</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="stat-gradient-1 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">Total Retailers</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    {{ stats.total_retailers }}
                </div>
                <div class="text-xs text-blue-200 mt-1">
                    {{ stats.active_retailers }} active
                </div>
            </div>
            <div class="stat-gradient-2 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Today's Transactions</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    {{ stats.today_transactions }}
                </div>
                <div class="text-xs text-green-200 mt-1">
                    {{ stats.today_success }} succeeded
                </div>
            </div>
            <div class="stat-gradient-3 rounded-2xl p-5 card-hover">
                <div class="text-sm text-blue-100">Today's Volume</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    £ {{ Number(stats.today_volume).toFixed(2) }}
                </div>
                <div class="text-xs text-blue-200 mt-1">
                    Successful recharges
                </div>
            </div>
            <div class="stat-gradient-4 rounded-2xl p-5 card-hover">
                <div class="text-sm text-green-100">Success Rate</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    {{ stats.success_rate }}%
                </div>
                <div class="text-xs text-green-200 mt-1">
                    All time · {{ stats.total_transactions }} txns
                </div>
            </div>
        </div>

        <!-- Secondary analytics chips -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-xs text-ink-500 uppercase tracking-wider">
                    Monthly Revenue
                </div>
                <div class="text-xl font-bold text-ink-900 mt-1">
                    £ {{ Number(stats.monthly_revenue).toFixed(2) }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-xs text-ink-500 uppercase tracking-wider">
                    Pending Top-ups
                </div>
                <div class="text-xl font-bold text-ink-900 mt-1">
                    {{ stats.pending_topups }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-xs text-ink-500 uppercase tracking-wider">
                    Pending KYC
                </div>
                <div class="text-xl font-bold text-ink-900 mt-1">
                    {{ stats.pending_kyc }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-xs text-ink-500 uppercase tracking-wider">
                    Total Transactions
                </div>
                <div class="text-xl font-bold text-ink-900 mt-1">
                    {{ stats.total_transactions }}
                </div>
            </div>
        </div>

        <!-- Provider Balances -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- DingConnect Balance -->
            <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-ink-900">
                            DingConnect Balance
                        </h3>
                        <p class="text-ink-500 text-sm mt-1">
                            Your wholesale balance with DingConnect
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-2xl font-bold text-primary-light">
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

            <!-- Prepay Nation Balance -->
            <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-ink-900">
                            Prepay Nation Balance
                        </h3>
                        <p class="text-ink-500 text-sm mt-1">
                            Your wholesale balance with Prepay Nation
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-2xl font-bold text-primary-light">
                            {{
                                stats.prepay_nation_balance?.success
                                    ? stats.prepay_nation_balance.balance +
                                      " " +
                                      stats.prepay_nation_balance.currency
                                    : "N/A"
                            }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Retailers & Recent Transactions -->
        <div class="grid lg:grid-cols-2 gap-6">
            <div
                class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
            >
                <div class="p-6 border-b border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900">
                        Top Retailers This Month
                    </h3>
                </div>
                <div class="divide-y divide-dark-600">
                    <div
                        v-for="retailer in topRetailers"
                        :key="retailer.id"
                        class="p-4 flex items-center justify-between hover:bg-surface-2 transition"
                    >
                        <div>
                            <div class="font-medium text-ink-900">
                                {{ retailer.shop_name || retailer.name }}
                            </div>
                            <div class="text-sm text-ink-500">
                                {{ retailer.phone }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-primary-light">
                                {{ retailer.month_success }}
                                <span class="text-ink-500 font-normal"
                                    >/ {{ retailer.month_transactions }}</span
                                >
                            </div>
                            <div class="text-xs text-ink-500">
                                successful / total
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="topRetailers && topRetailers.length === 0"
                        class="p-6 text-center text-ink-500"
                    >
                        No data yet
                    </div>
                </div>
            </div>

            <div
                class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
            >
                <div
                    class="p-6 border-b border-surface-3 flex items-center justify-between"
                >
                    <h3 class="text-lg font-semibold text-ink-900">
                        Recent Transactions
                    </h3>
                    <Link
                        href="/admin/transactions"
                        class="text-sm text-primary-light hover:text-primary transition"
                        >View All</Link
                    >
                </div>
                <div class="divide-y divide-dark-600">
                    <div v-if="recentTransactions">
                        <div
                            v-for="txn in (recentTransactions || []).slice(
                                0,
                                8,
                            )"
                            :key="txn.id"
                            class="p-4 flex items-center justify-between hover:bg-surface-2 transition"
                        >
                            <div class="flex items-center space-x-3">
                                <div
                                    class="w-8 h-8 bg-surface-3 rounded-lg flex items-center justify-center text-sm font-semibold text-primary-light"
                                >
                                    {{ (txn.operator || "?").charAt(0) }}
                                </div>
                                <div>
                                    <div
                                        class="text-sm font-medium text-ink-900"
                                    >
                                        {{ txn.mobile }}
                                    </div>
                                    <div class="text-xs text-ink-500">
                                        {{ txn.retailer || "Unknown" }}
                                        <span v-if="txn.country">
                                            · {{ txn.country }}</span
                                        >
                                        · {{ txn.created_at }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-ink-900">
                                    £ {{ Number(txn.amount).toFixed(2) }}
                                </div>
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-green-200 text-green-600'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-600'
                                              : 'bg-yellow-500/20 text-yellow-600',
                                    ]"
                                >
                                    {{ txn.status }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="
                                !recentTransactions ||
                                recentTransactions.length === 0
                            "
                            class="p-6 text-center text-ink-500"
                        >
                            No transactions yet
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div
            v-if="chartData && chartData.length"
            class="bg-surface-2 rounded-2xl border border-surface-3 p-6"
        >
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink-900">Revenue</h3>
                    <p class="text-xs text-ink-500">
                        Last 30 days · successful transactions
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold text-ink-900">
                        {{ formatCurrency(totalRevenue) }}
                    </p>
                    <p class="text-xs text-ink-500">Total</p>
                </div>
            </div>

            <div class="flex gap-3">
                <!-- Y axis -->
                <div
                    class="flex flex-col justify-between h-48 text-[10px] text-ink-500 text-right w-10"
                >
                    <span>{{ formatCompact(maxRevenue) }}</span>
                    <span>{{ formatCompact(maxRevenue / 2) }}</span>
                    <span>0</span>
                </div>

                <!-- Bars -->
                <div class="flex-1">
                    <div
                        class="relative flex items-end gap-1 h-48 border-b border-l border-surface-3"
                    >
                        <!-- gridlines -->
                        <div
                            class="absolute inset-x-0 top-1/2 border-t border-dashed border-surface-3/60"
                        ></div>

                        <div
                            v-for="point in chartData"
                            :key="point.date"
                            class="group relative flex-1 h-full flex items-end"
                        >
                            <div
                                class="w-full bg-primary opacity-70 group-hover:opacity-100 rounded-t transition-all"
                                :style="{ height: barHeight(point.revenue) }"
                            ></div>

                            <!-- Tooltip -->
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 whitespace-nowrap rounded-lg bg-surface-0 border border-surface-3 px-3 py-2 text-xs text-ink-900 shadow-lg"
                            >
                                <p class="font-semibold">
                                    {{ formatDate(point.date) }}
                                </p>
                                <p>{{ formatCurrency(point.revenue) }}</p>
                                <p class="text-ink-500">
                                    {{ point.count }} transaction{{
                                        point.count === 1 ? "" : "s"
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- X labels -->
                    <div class="flex gap-1 mt-2">
                        <div
                            v-for="(point, i) in chartData"
                            :key="point.date"
                            class="flex-1 text-center"
                        >
                            <span
                                v-if="showLabel(i)"
                                class="text-[10px] text-ink-500 whitespace-nowrap"
                            >
                                {{ formatDate(point.date) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
