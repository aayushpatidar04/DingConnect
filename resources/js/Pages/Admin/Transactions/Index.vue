<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed, reactive } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({
    transactions: Object,
    stats: Object,
    retailers: Array,
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    status: props.filters.status ?? "",
    retailer_id: props.filters.retailer_id ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (filters.from) params.set("from", filters.from);
    if (filters.to) params.set("to", filters.to);
    if (filters.status) params.set("status", filters.status);
    if (filters.retailer_id) params.set("retailer_id", filters.retailer_id);

    const qs = params.toString();
    return "/admin/transactions/export" + (qs ? "?" + qs : "");
});
</script>

<template>
    <Head title="Transactions - Admin" />
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Transactions</h1>
            <p class="text-ink-500">All platform transactions</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-sm text-ink-500">Total</div>
                <div class="text-xl font-bold text-ink-900">
                    {{ stats.total }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-sm text-ink-500">Success</div>
                <div class="text-xl font-bold text-accent-light">
                    {{ stats.success }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-sm text-ink-500">Failed</div>
                <div class="text-xl font-bold text-red-600">
                    {{ stats.failed }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-sm text-ink-500">Pending</div>
                <div class="text-xl font-bold text-yellow-600">
                    {{ stats.pending }}
                </div>
            </div>
            <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3">
                <div class="text-sm text-blue-100">Total Volume</div>
                <div class="text-xl font-bold text-primary-light">
                    £ {{ Number(stats.total_volume).toFixed(2) }}
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form
            method="GET"
            class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end"
        >
            <div>
                <label class="text-xs text-ink-500 block mb-1">Status</label
                ><select
                    name="status" v-model="filters.status"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                >
                    <option value="">All</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">Retailer</label
                ><select
                    name="retailer_id" v-model="filters.retailer_id"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                >
                    <option value="">All</option>
                    <option v-for="r in retailers" :value="r.id" :key="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">From</label
                ><input v-model="filters.from"
                    type="date"
                    name="from"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">To</label
                ><input v-model="filters.to"
                    type="date"
                    name="to"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                />
            </div>
            <button
                type="submit"
                class="px-4 py-2 bg-primary text-ink-100 rounded-lg hover:bg-primary-dark transition text-sm"
            >
                Filter
            </button>
            <a
                :href="exportUrl"
                class="px-4 py-2 bg-accent text-ink-100 rounded-lg hover:bg-accent-dark transition text-sm"
            >
                Export
            </a>
        </form>

        <!-- Table -->
        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-600">
                    <thead class="bg-surface-3">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                ID
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Date
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Retailer
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Mobile
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Operator
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Amount
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Receipt
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="txn in transactions.data"
                            :key="txn.id"
                            class="hover:bg-surface-2 transition"
                        >
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.id }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.created_at }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.user?.name || "-" }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-mono text-ink-700"
                            >
                                {{ txn.mobile_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.operator?.name || "-" }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-medium text-ink-900"
                            >
                                £ {{ Number(txn.amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-green-200 text-green-600'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-600'
                                              : 'bg-yellow-500/20 text-yellow-600',
                                    ]"
                                    >{{ txn.status }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.receipt_number || "-" }}
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td
                                colspan="9"
                                class="px-4 py-8 text-center text-ink-500"
                            >
                                No transactions found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div
                v-if="transactions.links"
                class="mt-4 flex justify-center gap-2 p-4"
            >
                <template v-for="link in transactions.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm border border-surface-3 transition"
                        :class="
                            link.active
                                ? 'bg-primary text-ink-100 border-primary'
                                : 'bg-surface-2 text-ink-500 hover:bg-surface-2'
                        "
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
