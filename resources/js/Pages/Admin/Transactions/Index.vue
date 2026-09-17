<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({
    transactions: Object,
    stats: Object,
    retailers: Array,
});
</script>

<template>
    <Head title="Transactions - Admin" />
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">Transactions</h1>
            <p class="text-dark-300">All platform transactions</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-dark-800 rounded-2xl p-4 border border-dark-600">
                <div class="text-sm text-dark-300">Total</div>
                <div class="text-xl font-bold text-white">
                    {{ stats.total }}
                </div>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 border border-dark-600">
                <div class="text-sm text-dark-300">Success</div>
                <div class="text-xl font-bold text-accent-light">
                    {{ stats.success }}
                </div>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 border border-dark-600">
                <div class="text-sm text-dark-300">Failed</div>
                <div class="text-xl font-bold text-red-400">
                    {{ stats.failed }}
                </div>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 border border-dark-600">
                <div class="text-sm text-dark-300">Pending</div>
                <div class="text-xl font-bold text-yellow-400">
                    {{ stats.pending }}
                </div>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 border border-dark-600">
                <div class="text-sm text-blue-100">Total Volume</div>
                <div class="text-xl font-bold text-primary-light">
                    £ {{ Number(stats.total_volume).toFixed(2) }}
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form
            method="GET"
            class="bg-dark-800 rounded-2xl p-4 border border-dark-600 flex flex-wrap gap-3 items-end"
        >
            <div>
                <label class="text-xs text-dark-300 block mb-1">Status</label
                ><select
                    name="status"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                >
                    <option value="">All</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-dark-300 block mb-1">Retailer</label
                ><select
                    name="retailer_id"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                >
                    <option value="">All</option>
                    <option v-for="r in retailers" :value="r.id" :key="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="text-xs text-dark-300 block mb-1">From</label
                ><input
                    type="date"
                    name="from"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                />
            </div>
            <div>
                <label class="text-xs text-dark-300 block mb-1">To</label
                ><input
                    type="date"
                    name="to"
                    class="border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                />
            </div>
            <button
                type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition text-sm"
            >
                Filter
            </button>
            <Link
                href="/admin/transactions/export"
                class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition text-sm"
                >Export</Link
            >
        </form>

        <!-- Table -->
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
                                ID
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Date
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Retailer
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
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Receipt
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="txn in transactions.data"
                            :key="txn.id"
                            class="hover:bg-dark-700 transition"
                        >
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.id }}
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.created_at }}
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.user?.name || "-" }}
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
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-accent/20 text-accent-light'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-400'
                                              : 'bg-yellow-500/20 text-yellow-400',
                                    ]"
                                    >{{ txn.status }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ txn.receipt_number || "-" }}
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td
                                colspan="9"
                                class="px-4 py-8 text-center text-dark-400"
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
                        class="px-3 py-1 rounded-lg text-sm border border-dark-600 transition"
                        :class="
                            link.active
                                ? 'bg-primary text-white border-primary'
                                : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                        "
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm bg-dark-800 text-dark-500"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
