<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputError from "@/Components/InputError.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    retailer: Object,
    transactions: Object,
    operators: { type: Array, default: () => [] },
    txnFilters: { type: Object, default: () => ({}) },
    ledgers: Object,
    ledgerFilters: { type: Object, default: () => ({}) },
});

const creditForm = useForm({ amount: "", description: "" });

const txnFilters = reactive({
    search: props.txnFilters.search ?? "",
    status: props.txnFilters.status ?? "",
    operator_id: props.txnFilters.operator_id ?? "",
    from: props.txnFilters.from ?? "",
    to: props.txnFilters.to ?? "",
});

const ledgerFilters = reactive({
    type: props.ledgerFilters.type ?? "",
    description: props.ledgerFilters.description ?? "",
    from: props.ledgerFilters.from ?? "",
    to: props.ledgerFilters.to ?? "",
});

const operatorOptions = computed(() =>
    props.operators.map((o) => ({
        id: o.id,
        label: o.name,
        hint: o.country?.iso_code,
    })),
);

const hasTxnFilters = computed(() => Object.values(txnFilters).some((v) => v !== ""));
const hasLedgerFilters = computed(() => Object.values(ledgerFilters).some((v) => v !== ""));

const activeTxnParams = () =>
    Object.fromEntries(Object.entries(txnFilters).filter(([, v]) => v !== ""));
const activeLedgerParams = () =>
    Object.fromEntries(Object.entries(ledgerFilters).filter(([, v]) => v !== ""));
const allParams = () => ({ ...activeTxnParams(), ...activeLedgerParams() });

let timer = null;
watch(
    [txnFilters, ledgerFilters],
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                `/admin/retailers/${props.retailer.id}`,
                allParams(),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);
    },
    { deep: true },
);

function resetTxn() {
    Object.keys(txnFilters).forEach((k) => (txnFilters[k] = ""));
}

function resetLedger() {
    Object.keys(ledgerFilters).forEach((k) => (ledgerFilters[k] = ""));
}

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

const ledgerTypeBadge = (type) => {
    switch (type) {
        case 'topup':
        case 'credit':
            return 'bg-green-200 text-green-600';
        case 'debit':
        case 'refund':
            return 'bg-red-500/20 text-red-600';
        default:
            return 'bg-yellow-500/20 text-yellow-600';
    }
};

const inputClass =
    "border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark";
</script>

<template>
    <Head :title="`${retailer.name} - Retailer Details`" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <Link
                href="/admin/retailers"
                class="text-primary-light hover:text-primary transition"
                >← Back to Retailers</Link
            >
            <div class="flex gap-4 items-center">
                <Link
                    :href="`/admin/retailers/${retailer.id}/edit`"
                    class="ml-4 px-4 py-2 bg-primary text-ink-100 rounded-lg font-semibold hover:bg-primary-dark transition text-sm"
                    >Edit</Link
                >
                <div>
                    <h1 class="text-3xl font-bold text-ink-900">
                        {{ retailer.name }}
                    </h1>
                    <p class="text-ink-500">
                        {{ retailer.email }} · {{ retailer.phone }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
                        Retailer Information
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-ink-500">Shop Name:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.shop_name || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">Address:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.address || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">City:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.city || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">County:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.county || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">Postcode:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.postcode || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">VAT:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.vat_number || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500">UTR:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.utr_number || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-ink-500"
                                >Company Registration Number:</span
                            >
                            <p class="font-medium text-ink-900">
                                {{ retailer.company_reg_number || "-" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Filters -->
                <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-ink-500 block mb-1">Search</label>
                        <input
                            v-model="txnFilters.search"
                            type="search"
                            placeholder="Mobile or receipt number"
                            :class="[inputClass, 'w-full']"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">Operator</label>
                        <SearchableSelect
                            v-model="txnFilters.operator_id"
                            :options="operatorOptions"
                            all-label="All Operators"
                            search-placeholder="Search operators..."
                        />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">Status</label>
                        <select v-model="txnFilters.status" :class="inputClass">
                            <option value="">All</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">From</label>
                        <input v-model="txnFilters.from" type="date" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">To</label>
                        <input v-model="txnFilters.to" type="date" :class="inputClass" />
                    </div>
                    <button
                        v-if="hasTxnFilters"
                        type="button"
                        @click="resetTxn"
                        class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
                    >
                        Reset
                    </button>
                </div>

                <!-- Transactions Table -->
                <div class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-dark-600">
                            <thead class="bg-surface-3">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Date
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Mobile
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Operator
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Amount
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Receipt
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-700 uppercase">
                                        Actions
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
                                        {{ formatDate(txn.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono text-ink-700">
                                        {{ txn.mobile_number }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ txn.operator?.name || "-" }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-ink-900">
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
                                        >
                                            {{ txn.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ txn.receipt_number || "-" }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="`/admin/transactions/${txn.id}`"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-medium hover:bg-primary-dark transition"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!transactions.data?.length">
                                    <td
                                        colspan="8"
                                        class="px-4 py-8 text-center text-ink-500"
                                    >
                                        No transactions found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="transactions.links" class="mt-4 flex justify-center gap-2 p-4">
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

                <!-- Ledger Filters -->
                <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-ink-500 block mb-1">Type</label>
                        <input
                            v-model="ledgerFilters.type"
                            type="search"
                            placeholder="e.g. topup, debit, refund"
                            :class="[inputClass, 'w-full']"
                        />
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-ink-500 block mb-1">Description</label>
                        <input
                            v-model="ledgerFilters.description"
                            type="search"
                            placeholder="Search description..."
                            :class="[inputClass, 'w-full']"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">From</label>
                        <input v-model="ledgerFilters.from" type="date" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">To</label>
                        <input v-model="ledgerFilters.to" type="date" :class="inputClass" />
                    </div>
                    <button
                        v-if="hasLedgerFilters"
                        type="button"
                        @click="resetLedger"
                        class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
                    >
                        Reset
                    </button>
                </div>

                <!-- Wallet Ledgers Table -->
                <div class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-dark-600">
                            <thead class="bg-surface-3">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Date
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Type
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Amount
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Balance Before
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Balance After
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Description
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dark-600">
                                <tr
                                    v-for="ledger in ledgers.data"
                                    :key="ledger.id"
                                    class="hover:bg-surface-2 transition"
                                >
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ ledger.id }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ formatDate(ledger.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-ink-700">
                                        <span
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                ledgerTypeBadge(ledger.type),
                                            ]"
                                        >
                                            {{ ledger.type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-ink-900">
                                        £ {{ Number(ledger.amount).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        £ {{ Number(ledger.balance_before).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        £ {{ Number(ledger.balance_after).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ ledger.description || "-" }}
                                    </td>
                                </tr>
                                <tr v-if="!ledgers.data?.length">
                                    <td
                                        colspan="7"
                                        class="px-4 py-8 text-center text-ink-500"
                                    >
                                        No wallet ledger entries found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="ledgers.links" class="mt-4 flex justify-center gap-2 p-4">
                        <template v-for="link in ledgers.links" :key="link.label">
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

            <div class="space-y-6">
                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
                        Wallet
                    </h3>
                    <div class="text-3xl font-bold text-primary-light">
                        £
                        {{ Number(retailer.wallet?.balance || 0).toFixed(2) }}
                    </div>
                    <form
                        @submit.prevent="
                            creditForm.post(
                                `/admin/retailers/${retailer.id}/credit`,
                                {
                                    onSuccess: () => {
                                        creditForm.reset();
                                    },
                                },
                            )
                        "
                        class="mt-4 space-y-3"
                    >
                        <input
                            v-model="creditForm.amount"
                            type="number"
                            step="0.01"
                            placeholder="Amount"
                            class="w-full px-3 py-2 border border-surface-3 rounded-lg bg-surface-3 text-ink-900 input-dark"
                            required
                        />
                        <input
                            v-model="creditForm.description"
                            type="text"
                            placeholder="Description (optional)"
                            class="w-full px-3 py-2 border border-surface-3 rounded-lg bg-surface-3 text-ink-900 input-dark"
                        />
                        <button
                            type="submit"
                            :disabled="creditForm.processing"
                            class="w-full py-2 bg-primary text-ink-100 rounded-lg hover:bg-primary-dark disabled:opacity-60 transition"
                        >
                            Credit Wallet
                        </button>
                    </form>
                </div>

                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
                        Verification Status
                    </h3>
                    <span
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-medium',
                            retailer.kyc_status === 'approved'
                                ? 'bg-green-200 text-green-600'
                                : retailer.kyc_status === 'rejected'
                                  ? 'bg-red-500/20 text-red-600'
                                  : 'bg-yellow-500/20 text-yellow-600',
                        ]"
                    >
                        {{ retailer.kyc_status }}
                    </span>
                    <p v-if="retailer.kyc_rejection_reason" class="text-sm text-red-600 mt-2">
                        {{ retailer.kyc_rejection_reason }}
                    </p>
                    <div class="mt-4 space-y-2">
                        <form :action="`/admin/retailers/${retailer.id}/kyc`" method="POST">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                            <input type="hidden" name="action" value="approve" />
                            <button type="submit" class="w-full py-2 bg-accent text-ink-100 rounded-lg hover:bg-accent-dark transition">
                                Approve
                            </button>
                        </form>
                        <form :action="`/admin/retailers/${retailer.id}/kyc`" method="POST">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                            <input type="hidden" name="action" value="reject" />
                            <input
                                type="text"
                                name="rejection_reason"
                                placeholder="Rejection reason"
                                class="w-full px-3 py-2 border border-surface-3 rounded-lg mb-2 text-sm bg-surface-3 text-ink-900 input-dark"
                            />
                            <InputError class="mt-2" :message="$page.props.errors?.rejection_reason" />
                            <button type="submit" class="w-full py-2 bg-red-600 text-ink-100 rounded-lg hover:bg-red-700 transition text-sm">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
