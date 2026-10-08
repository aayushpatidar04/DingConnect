<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { reactive, watch, computed } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    transactions: Object,
    operators: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters.search ?? "",
    status: props.filters.status ?? "",
    type: props.filters.type ?? "",
    operator_id: props.filters.operator_id ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

const hasFilters = computed(() => Object.values(filters).some((v) => v !== ""));

let timer = null;
function apply() {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== ""),
    );
    router.get("/retailer/transactions", params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(apply, 400);
    },
    { deep: true },
);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ""));
}

const inputClass = "border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark";

const formatDate = (date) => {
    if (!date) return "-";

    return new Date(date).toLocaleString("en-GB", {
        timeZone: "Europe/London",
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};
</script>

<template>
    <Head title="Transactions - MK Network" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">
                Transaction History
            </h1>
            <p class="text-ink-500">View all your recharges and transactions</p>
        </div>

        <!-- Filters -->
        <div
            class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end"
        >
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-ink-500 block mb-1">Search</label>
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Mobile or receipt number"
                    :class="[inputClass, 'w-full']"
                />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">Status</label>
                <select v-model="filters.status" :class="inputClass">
                    <option value="">All Status</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">Type</label>
                <select v-model="filters.type" :class="inputClass">
                    <option value="">All Types</option>
                    <option value="Immediate">Immediate</option>
                    <option value="ReadReceipt">Read Receipt (PIN)</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">Operator</label>
                <select v-model="filters.operator_id" :class="inputClass">
                    <option value="">All Operators</option>
                    <option v-for="op in operators" :key="op.id" :value="op.id">
                        {{ op.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">From</label>
                <input v-model="filters.from" type="date" :class="inputClass" />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">To</label>
                <input v-model="filters.to" type="date" :class="inputClass" />
            </div>
            <button
                v-if="hasFilters"
                type="button"
                @click="reset"
                class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
            >
                Reset
            </button>
        </div>

        <!-- Table -->
        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th
                                v-for="h in [
                                    'Receipt',
                                    'Date',
                                    'Mobile',
                                    'Operator',
                                    'Amount',
                                    'Type',
                                    'Status',
                                ]"
                                :key="h"
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                {{ h }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr
                            v-for="txn in transactions.data"
                            :key="txn.id"
                            class="hover:bg-surface-3/50 transition cursor-pointer"
                            @click="
                                router.visit(
                                    route('retailer.transactions.show', txn.id),
                                )
                            "
                        >
                            <td
                                class="px-4 py-3 text-sm font-mono text-primary-light"
                            >
                                {{ txn.receipt_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ formatDate(txn.created_at) }}
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
                            <td class="px-4 py-3 text-xs text-ink-500">
                                {{ txn.redemption_type || "Immediate" }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-green-200 text-green-600'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-600'
                                              : 'bg-yellow-500/20 text-yellow-600',
                                    ]"
                                    >{{ txn.status }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td
                                colspan="7"
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
                v-if="transactions.total > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-surface-3"
            >
                <p class="text-xs text-ink-500">
                    Showing {{ transactions.from }}–{{ transactions.to }} of
                    {{ transactions.total }}
                </p>

                <div
                    v-if="transactions.last_page > 1"
                    class="flex flex-wrap justify-center gap-2"
                >
                    <template
                        v-for="link in transactions.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-sm border border-surface-3 transition"
                            :class="
                                link.active
                                    ? 'bg-primary text-ink-100 border-primary'
                                    : 'bg-surface-2 text-ink-500 hover:bg-surface-3'
                            "
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500 opacity-50"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
