<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({ retailer: Object });

const creditForm = useForm({ amount: "", description: "" });
</script>

<template>
    <Head :title="`${retailer.name} - Retailer Details`" />
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
                    class="ml-4 px-4 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-primary-dark transition text-sm"
                    >Edit</Link
                >
    
                <div>
                    <h1 class="text-3xl font-bold text-white">
                        {{ retailer.name }}
                    </h1>
                    <p class="text-dark-300">
                        {{ retailer.email }} · {{ retailer.phone }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
                    <h3 class="text-lg font-semibold text-white mb-4">
                        Retailer Information
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-dark-400">Shop Name:</span>
                            <p class="font-medium text-white">
                                {{ retailer.shop_name || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400">Address:</span>
                            <p class="font-medium text-white">
                                {{ retailer.address || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400">City:</span>
                            <p class="font-medium text-white">
                                {{ retailer.city || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400">County:</span>
                            <p class="font-medium text-white">
                                {{ retailer.county || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400">VAT:</span>
                            <p class="font-medium text-white">
                                {{ retailer.vat_number || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400">UTR:</span>
                            <p class="font-medium text-white">
                                {{ retailer.utr_number || "-" }}
                            </p>
                        </div>
                        <div>
                            <span class="text-dark-400"
                                >Company Registration Number:</span
                            >
                            <p class="font-medium text-white">
                                {{ retailer.company_reg_number || "-" }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
                    <h3 class="text-lg font-semibold text-white mb-4">
                        Recent Transactions
                    </h3>
                    <div
                        v-if="retailer.transactions?.length"
                        class="divide-y divide-dark-600"
                    >
                        <div
                            v-for="txn in retailer.transactions.slice(0, 10)"
                            :key="txn.id"
                            class="py-3 flex items-center justify-between"
                        >
                            <div>
                                <div class="font-medium text-white">
                                    {{ txn.mobile_number }}
                                </div>
                                <div class="text-sm text-dark-400">
                                    {{ txn.operator?.name || "Unknown" }} ·
                                    {{ txn.created_at }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-white">
                                    £ {{ Number(txn.amount).toFixed(2) }}
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
                    <p v-else class="text-dark-400">No transactions yet</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
                    <h3 class="text-lg font-semibold text-white mb-4">
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
                            )
                        "
                        class="mt-4 space-y-3"
                    >
                        <input
                            v-model="creditForm.amount"
                            type="number"
                            step="0.01"
                            placeholder="Amount"
                            class="w-full px-3 py-2 border border-dark-600 rounded-lg bg-dark-700 text-white input-dark"
                            required
                        />
                        <input
                            v-model="creditForm.description"
                            type="text"
                            placeholder="Description (optional)"
                            class="w-full px-3 py-2 border border-dark-600 rounded-lg bg-dark-700 text-white input-dark"
                        />
                        <button
                            type="submit"
                            :disabled="creditForm.processing"
                            class="w-full py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:opacity-50 transition"
                        >
                            Credit Wallet
                        </button>
                    </form>
                </div>

                <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
                    <h3 class="text-lg font-semibold text-white mb-4">
                        KYC Status
                    </h3>
                    <span
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-medium',
                            retailer.kyc_status === 'approved'
                                ? 'bg-accent/20 text-accent-light'
                                : retailer.kyc_status === 'rejected'
                                  ? 'bg-red-500/20 text-red-400'
                                  : 'bg-yellow-500/20 text-yellow-400',
                        ]"
                    >
                        {{ retailer.kyc_status }}
                    </span>
                    <p
                        v-if="retailer.kyc_rejection_reason"
                        class="text-sm text-red-400 mt-2"
                    >
                        {{ retailer.kyc_rejection_reason }}
                    </p>
                    <div class="mt-4 space-y-2">
                        <form
                            :action="`/admin/retailers/${retailer.id}/kyc`"
                            method="POST"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                :value="$page.props.csrf_token"
                            />
                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            />
                            <button
                                type="submit"
                                class="w-full py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition"
                            >
                                Approve KYC
                            </button>
                        </form>
                        <form
                            :action="`/admin/retailers/${retailer.id}/kyc`"
                            method="POST"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                :value="$page.props.csrf_token"
                            />
                            <input type="hidden" name="action" value="reject" />
                            <input
                                type="text"
                                name="rejection_reason"
                                placeholder="Rejection reason"
                                class="w-full px-3 py-2 border border-dark-600 rounded-lg mb-2 text-sm bg-dark-700 text-white input-dark"
                            />
                            <button
                                type="submit"
                                class="w-full py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm"
                            >
                                Reject KYC
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
