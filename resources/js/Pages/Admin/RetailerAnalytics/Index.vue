<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, onUnmounted, reactive, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    operators: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    connectionDistribution: { type: Array, default: () => [] },
    networkBreakdown: { type: Array, default: () => [] },
    highestRetailers: { type: Array, default: () => [] },
    lowestRetailers: { type: Array, default: () => [] },
});

const filterKeys = [
    "search",
    "network_id",
    "connections",
    "status",
    "kyc_status",
    "city",
    "registered_from",
    "registered_to",
    "activity_from",
    "activity_to",
];

const filters = reactive(
    Object.fromEntries(filterKeys.map((key) => [key, props.filters[key] ?? ""])),
);

const activeParams = () =>
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ""));

const hasFilters = computed(() => Object.keys(activeParams()).length > 0);

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get("/admin/analytics", activeParams(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);
    },
    { deep: true },
);

onUnmounted(() => clearTimeout(timer));

function resetFilters() {
    filterKeys.forEach((key) => {
        filters[key] = "";
    });
}

const reports = computed(() => [
    {
        title: "Highest connections",
        description: "Retailers with the most network connections.",
        rows: props.highestRetailers,
    },
    {
        title: "Lowest connections",
        description: "Retailers with the fewest connections in this group.",
        rows: props.lowestRetailers,
    },
]);

const numberFormat = new Intl.NumberFormat("en-GB");
const formatNumber = (value) => numberFormat.format(Number(value ?? 0));
const formatCurrency = (value) =>
    new Intl.NumberFormat("en-GB", {
        style: "currency",
        currency: "GBP",
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));

const selectClass =
    "w-full border border-surface-3 rounded-lg px-3 py-2.5 bg-surface-3 text-ink-900 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary";
const inputClass =
    "w-full border border-surface-3 rounded-lg px-3 py-2.5 bg-surface-3 text-ink-900 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary";
const labelClass = "block text-xs font-medium text-ink-500 mb-1.5";
</script>

<template>
    <Head title="Retailer Analytics - Admin" />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">
                Retailer Analytics
            </h1>
            <p class="text-ink-500">
                Explore retailer connections, network mix, activity, and performance.
            </p>
        </div>

        <section class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-ink-900">Filters</h2>
                    <p class="text-sm text-ink-500">
                        Filters apply to both connection reports and all retailer analytics.
                    </p>
                    <p class="text-xs text-ink-500 mt-1">
                        When a network is selected, connection totals and thresholds count only that network.
                    </p>
                </div>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
                    @click="resetFilters"
                >
                    Reset filters
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <label>
                    <span :class="labelClass">Search retailer</span>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Name, shop, email, phone..."
                        :class="inputClass"
                    />
                </label>

                <label>
                    <span :class="labelClass">Network</span>
                    <select v-model="filters.network_id" :class="selectClass">
                        <option value="">All networks</option>
                        <option
                            v-for="operator in operators"
                            :key="operator.id"
                            :value="operator.id"
                        >
                            {{ operator.label }}
                        </option>
                    </select>
                </label>

                <label>
                    <span :class="labelClass">Minimum connections</span>
                    <select v-model="filters.connections" :class="selectClass">
                        <option value="">Any number</option>
                        <option value="5">5+ connections</option>
                        <option value="10">10+ connections</option>
                        <option value="20">20+ connections</option>
                    </select>
                </label>

                <label>
                    <span :class="labelClass">Retailer status</span>
                    <select v-model="filters.status" :class="selectClass">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>

                <label>
                    <span :class="labelClass">Verification status</span>
                    <select v-model="filters.kyc_status" :class="selectClass">
                        <option value="">All Verification statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </label>

                <label>
                    <span :class="labelClass">City</span>
                    <select v-model="filters.city" :class="selectClass">
                        <option value="">All cities</option>
                        <option v-for="city in cities" :key="city" :value="city">
                            {{ city }}
                        </option>
                    </select>
                </label>

                <label>
                    <span :class="labelClass">Registered from</span>
                    <input
                        v-model="filters.registered_from"
                        type="date"
                        :class="inputClass"
                    />
                </label>

                <label>
                    <span :class="labelClass">Registered to</span>
                    <input
                        v-model="filters.registered_to"
                        type="date"
                        :class="inputClass"
                    />
                </label>

                <label>
                    <span :class="labelClass">Activity from</span>
                    <input
                        v-model="filters.activity_from"
                        type="date"
                        :class="inputClass"
                    />
                </label>

                <label>
                    <span :class="labelClass">Activity to</span>
                    <input
                        v-model="filters.activity_to"
                        type="date"
                        :class="inputClass"
                    />
                </label>
            </div>
        </section>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Matching retailers</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ formatNumber(stats.retailers) }}
                </p>
                <p class="text-xs text-ink-500 mt-1">
                    {{ formatNumber(stats.active_retailers) }} active
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Network connections</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ formatNumber(stats.total_connections) }}
                </p>
                <p class="text-xs text-ink-500 mt-1">Across matching retailers</p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Transactions</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ formatNumber(stats.total_transactions) }}
                </p>
                <p class="text-xs text-ink-500 mt-1">
                    {{ formatNumber(stats.successful_transactions) }} successful ·
                    {{ formatNumber(stats.failed_transactions) }} failed
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Successful transaction volume</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ formatCurrency(stats.successful_volume) }}
                </p>
                <p class="text-xs text-ink-500 mt-1">
                    {{ stats.success_rate }}% transaction success rate
                </p>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden">
                <div class="p-5 border-b border-surface-3">
                    <h2 class="text-lg font-semibold text-ink-900">
                        Connections by network
                    </h2>
                    <p class="text-sm text-ink-500">
                        Network mix among retailers matching the selected filters.
                    </p>
                </div>
                <div v-if="networkBreakdown.length" class="divide-y divide-surface-3">
                    <div
                        v-for="network in networkBreakdown"
                        :key="network.id"
                        class="flex items-center justify-between gap-4 px-5 py-3"
                    >
                        <span class="text-sm text-ink-700">{{ network.name }}</span>
                        <span class="text-sm font-semibold text-ink-900">
                            {{ formatNumber(network.connections) }}
                        </span>
                    </div>
                </div>
                <p v-else class="p-5 text-sm text-ink-500">
                    No network connections found for these filters.
                </p>
            </div>

            <div class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden">
                <div class="p-5 border-b border-surface-3">
                    <h2 class="text-lg font-semibold text-ink-900">
                        Retailers by connection count
                    </h2>
                    <p class="text-sm text-ink-500">
                        Retailer distribution across connection tiers.
                    </p>
                </div>
                <div class="divide-y divide-surface-3">
                    <div
                        v-for="band in connectionDistribution"
                        :key="band.label"
                        class="flex items-center justify-between gap-4 px-5 py-3"
                    >
                        <span class="text-sm text-ink-700">{{ band.label }}</span>
                        <span class="text-sm font-semibold text-ink-900">
                            {{ formatNumber(band.count) }} retailers
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-for="report in reports"
            :key="report.title"
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="p-5 border-b border-surface-3">
                <h2 class="text-lg font-semibold text-ink-900">{{ report.title }}</h2>
                <p class="text-sm text-ink-500">{{ report.description }}</p>
            </div>
            <div v-if="report.rows.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th
                                v-for="heading in ['Retailer', 'Connections', 'Location', 'Status', 'KYC', 'Transactions', 'Successful volume']"
                                :key="heading"
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap"
                            >
                                {{ heading }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="retailer in report.rows" :key="retailer.id">
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <Link
                                    :href="`/admin/retailers/${retailer.id}`"
                                    class="font-medium text-primary-light hover:underline"
                                >
                                    {{ retailer.shop_name || retailer.name }}
                                </Link>
                                <p class="text-xs text-ink-500">{{ retailer.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-ink-900">
                                {{ formatNumber(retailer.connections) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ retailer.city || "—" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ retailer.is_active ? "Active" : "Inactive" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 capitalize">
                                {{ retailer.kyc_status }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ formatNumber(retailer.transactions) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ formatCurrency(retailer.successful_volume) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="p-5 text-sm text-ink-500">
                No retailers match the selected filters.
            </p>
        </section>
    </div>
</template>
