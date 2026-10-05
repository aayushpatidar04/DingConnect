<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
defineOptions({ layout: RetailerLayout });

const props = defineProps({ transactions: Object });
</script>

<template>
    <Head title="Transactions - MK Network" />
    <div class="space-y-6">
        <h1 class="text-3xl font-bold text-ink-900 mb-1">Transaction History</h1>
        <p class="text-ink-500 mb-6">
            View all your recharges and transactions
        </p>

        <!-- Filters -->
        <form
            method="GET"
            class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end"
        >
            <div>
                <label class="text-xs text-ink-500 block mb-1">Status</label>
                <select
                    name="status"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                >
                    <option value="">All Status</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">From</label>
                <input
                    type="date"
                    name="from"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">To</label>
                <input
                    type="date"
                    name="to"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                />
            </div>
            <button
                type="submit"
                class="px-4 py-2 bg-primary text-ink-900 rounded-lg text-sm hover:bg-primary-dark transition"
            >
                Filter
            </button>
            <Link
                href="/retailer/transactions"
                class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-2 transition"
                >Reset</Link
            >
        </form>

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
                                Receipt
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Date
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
                                Type
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="txn in transactions.data"
                            :key="txn.id"
                            class="hover:bg-surface-2 transition cursor-pointer" @click="router.visit(route('retailer.transactions.show', txn.id))"
                        >
                            <td
                                class="px-4 py-3 text-sm font-mono text-primary-light"
                            >
                                {{ txn.receipt_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ txn.created_at }}
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
                                            ? 'bg-accent/20 text-accent-light'
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
                                colspan="6"
                                class="px-4 py-8 text-center text-ink-500"
                            >
                                No transactions found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
