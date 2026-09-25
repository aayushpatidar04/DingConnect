<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
defineOptions({ layout: RetailerLayout });

const props = defineProps({ transactions: Object });
</script>

<template>
    <Head title="Transactions - MK Network" />
    <div class="space-y-6">
        <h1 class="text-3xl font-bold text-white mb-1">Transaction History</h1>
        <p class="text-dark-300 mb-6">
            View all your recharges and transactions
        </p>

        <!-- Filters -->
        <form
            method="GET"
            class="bg-dark-800 rounded-2xl p-4 border border-dark-600 flex flex-wrap gap-3 items-end"
        >
            <div>
                <label class="text-xs text-dark-300 block mb-1">Status</label>
                <select
                    name="status"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                >
                    <option value="">All Status</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-dark-300 block mb-1">From</label>
                <input
                    type="date"
                    name="from"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                />
            </div>
            <div>
                <label class="text-xs text-dark-300 block mb-1">To</label>
                <input
                    type="date"
                    name="to"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                />
            </div>
            <button
                type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark transition"
            >
                Filter
            </button>
            <Link
                href="/retailer/transactions"
                class="px-4 py-2 border border-dark-600 rounded-lg text-sm text-dark-300 hover:bg-dark-700 transition"
                >Reset</Link
            >
        </form>

        <div
            class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-600">
                    <thead class="bg-dark-700">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Receipt
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Date
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Mobile
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Operator
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Amount
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Type
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="txn in transactions.data"
                            :key="txn.id"
                            class="hover:bg-dark-700 transition cursor-pointer" @click="router.visit(route('retailer.transactions.show', txn.id))"
                        >
                            <td
                                class="px-4 py-3 text-sm font-mono text-primary-light"
                            >
                                {{ txn.receipt_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.created_at }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-mono text-dark-200"
                            >
                                {{ txn.mobile_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.operator?.name || "-" }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-medium text-white"
                            >
                                £ {{ Number(txn.amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-dark-300">
                                {{ txn.redemption_type || "Immediate" }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-accent/20 text-accent-light'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-400'
                                              : 'bg-yellow-500/20 text-yellow-400',
                                    ]"
                                    >{{ txn.status }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-dark-400"
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
