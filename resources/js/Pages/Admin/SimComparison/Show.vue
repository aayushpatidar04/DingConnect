<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    comparison: Object,
});

const activeTab = ref("missing"); // 'matched', 'missing', 'additional'
</script>

<template>
    <Head :title="`Reconciliation Report #${comparison.id} - Admin Panel`" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2 mb-1">
                    <Link
                        href="/admin/sim-comparisons"
                        class="text-xs text-primary hover:underline font-medium"
                    >
                        ← Back to Reconciliation Reports
                    </Link>
                </div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">
                    Reconciliation Report #{{ comparison.id }}
                </h1>
                <p class="text-ink-500">
                    File: <strong class="text-ink-900">{{ comparison.file_name }}</strong> · Uploaded by {{ comparison.admin?.name }} on {{ comparison.created_at }}
                </p>
            </div>
        </div>

        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">Internal Records</p>
                <p class="text-3xl font-bold text-ink-900 mt-1">
                    {{ comparison.total_internal }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">Provider Report</p>
                <p class="text-3xl font-bold text-ink-900 mt-1">
                    {{ comparison.total_provider }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Matching</p>
                <p class="text-3xl font-bold text-emerald-600 mt-1">
                    {{ comparison.matched_count }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm border-l-4 border-l-red-500">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-600">Missing (Provider Missed)</p>
                <p class="text-3xl font-bold text-red-600 mt-1">
                    {{ comparison.missing_count }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Additional</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">
                    {{ comparison.additional_count }}
                </p>
            </div>
        </div>

        <!-- Tab Switcher -->
        <div class="flex space-x-2 border-b border-surface-3 pb-2">
            <button
                @click="activeTab = 'missing'"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition flex items-center space-x-2"
                :class="activeTab === 'missing' ? 'bg-red-500/10 text-red-600 border border-red-500/20' : 'text-ink-600 hover:bg-surface-3'"
            >
                <span>Missing from Provider Report ({{ comparison.report_data?.missing_from_provider?.length || 0 }})</span>
            </button>
            <button
                @click="activeTab = 'matched'"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition flex items-center space-x-2"
                :class="activeTab === 'matched' ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'text-ink-600 hover:bg-surface-3'"
            >
                <span>Matching Connections ({{ comparison.report_data?.matched?.length || 0 }})</span>
            </button>
            <button
                @click="activeTab = 'additional'"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition flex items-center space-x-2"
                :class="activeTab === 'additional' ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20' : 'text-ink-600 hover:bg-surface-3'"
            >
                <span>Additional / Unmatched in Report ({{ comparison.report_data?.additional_in_provider?.length || 0 }})</span>
            </button>
        </div>

        <!-- Tab 1: Missing From Provider Report (Provider Missed) -->
        <div v-if="activeTab === 'missing'" class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm space-y-4 p-6">
            <div>
                <h3 class="text-lg font-bold text-red-600">
                    Connections Recorded Internally but Missing from Network Provider Report
                </h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    These recharges were executed on the platform, but the network provider report did not include them. Follow up with the network provider to ensure commission is credited.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">Mobile Number</th>
                            <th class="p-4">Serial / Receipt</th>
                            <th class="p-4">Operator</th>
                            <th class="p-4">Country</th>
                            <th class="p-4">Retailer / Shop</th>
                            <th class="p-4">Internal Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="!comparison.report_data?.missing_from_provider || comparison.report_data.missing_from_provider.length === 0">
                            <td colspan="6" class="text-center py-10 text-ink-500 font-medium">
                                No missing connections found! All internal recharges are accounted for in the provider report.
                            </td>
                        </tr>
                        <tr
                            v-for="item in comparison.report_data.missing_from_provider"
                            :key="item.mobile"
                            class="hover:bg-surface-3/30 transition"
                        >
                            <td class="p-4 font-bold text-primary">
                                {{ item.mobile }}
                            </td>
                            <td class="p-4 font-mono text-xs">
                                {{ item.serial }}
                            </td>
                            <td class="p-4">
                                {{ item.provider }}
                            </td>
                            <td class="p-4">
                                {{ item.country }}
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-ink-900">
                                    {{ item.retailer?.shop_name || item.retailer?.name || '-' }}
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ item.retailer?.email }}
                                </div>
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ item.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Matched Connections -->
        <div v-if="activeTab === 'matched'" class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm space-y-4 p-6">
            <div>
                <h3 class="text-lg font-bold text-emerald-600">
                    Matching Connections (Verified in Both Internal & Provider Reports)
                </h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    These connections successfully match between platform recharges and the provider report.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">Mobile Number</th>
                            <th class="p-4">Serial</th>
                            <th class="p-4">Provider</th>
                            <th class="p-4">Country</th>
                            <th class="p-4">Retailer / Shop</th>
                            <th class="p-4">Internal Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="!comparison.report_data?.matched || comparison.report_data.matched.length === 0">
                            <td colspan="6" class="text-center py-10 text-ink-500 font-medium">
                                No matching connections found.
                            </td>
                        </tr>
                        <tr
                            v-for="item in comparison.report_data.matched"
                            :key="item.mobile"
                            class="hover:bg-surface-3/30 transition"
                        >
                            <td class="p-4 font-bold text-primary">
                                {{ item.mobile }}
                            </td>
                            <td class="p-4 font-mono text-xs">
                                {{ item.serial }}
                            </td>
                            <td class="p-4">
                                {{ item.provider }}
                            </td>
                            <td class="p-4">
                                {{ item.country }}
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-ink-900">
                                    {{ item.retailer?.shop_name || item.retailer?.name || '-' }}
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ item.retailer?.email }}
                                </div>
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ item.internal_date }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Additional / Unmatched in Provider Report -->
        <div v-if="activeTab === 'additional'" class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm space-y-4 p-6">
            <div>
                <h3 class="text-lg font-bold text-amber-600">
                    Additional Connections Reported by Provider (Not Found Internally)
                </h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    These numbers appear in the network provider report but were not recorded as recharges in the MK Network platform.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">Mobile Number</th>
                            <th class="p-4">Serial</th>
                            <th class="p-4">Provider</th>
                            <th class="p-4">Country</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="!comparison.report_data?.additional_in_provider || comparison.report_data.additional_in_provider.length === 0">
                            <td colspan="4" class="text-center py-10 text-ink-500 font-medium">
                                No additional unmatched connections reported by provider.
                            </td>
                        </tr>
                        <tr
                            v-for="item in comparison.report_data.additional_in_provider"
                            :key="item.mobile"
                            class="hover:bg-surface-3/30 transition"
                        >
                            <td class="p-4 font-bold text-primary">
                                {{ item.mobile }}
                            </td>
                            <td class="p-4 font-mono text-xs">
                                {{ item.serial }}
                            </td>
                            <td class="p-4">
                                {{ item.provider }}
                            </td>
                            <td class="p-4">
                                {{ item.country }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
