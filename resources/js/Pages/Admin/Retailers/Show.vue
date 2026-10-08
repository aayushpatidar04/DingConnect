<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputError from "@/Components/InputError.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({ retailer: Object });

const creditForm = useForm({ amount: "", description: "" });

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
                <div
                    class="bg-surface-2 rounded-2xl p-6 border border-surface-3"
                >
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

                <div
                    class="bg-surface-2 rounded-2xl p-6 border border-surface-3"
                >
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
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
                                <div class="font-medium text-ink-900">
                                    {{ txn.mobile_number }}
                                </div>
                                <div class="text-sm text-ink-500">
                                    {{ txn.operator?.name || "Unknown" }} ·
                                    {{ formatDate(txn.created_at) }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-ink-900">
                                    £ {{ Number(txn.amount).toFixed(2) }}
                                </div>
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        txn.status === 'success'
                                            ? 'bg-accent/20 text-accent-light'
                                            : txn.status === 'failed'
                                              ? 'bg-red-500/20 text-red-600'
                                              : 'bg-yellow-500/20 text-yellow-600',
                                    ]"
                                >
                                    {{ txn.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-ink-500">No transactions yet</p>
                </div>
            </div>

            <div class="space-y-6">
                <div
                    class="bg-surface-2 rounded-2xl p-6 border border-surface-3"
                >
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

                <div
                    class="bg-surface-2 rounded-2xl p-6 border border-surface-3"
                >
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
                        KYC Status
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
                    <p
                        v-if="retailer.kyc_rejection_reason"
                        class="text-sm text-red-600 mt-2"
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
                                class="w-full py-2 bg-accent text-ink-100 rounded-lg hover:bg-accent-dark transition"
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
                                class="w-full px-3 py-2 border border-surface-3 rounded-lg mb-2 text-sm bg-surface-3 text-ink-900 input-dark"
                            />
                            <InputError
                                class="mt-2"
                                :message="$page.props.errors?.rejection_reason"
                            />
                            <button
                                type="submit"
                                class="w-full py-2 bg-red-600 text-ink-100 rounded-lg hover:bg-red-700 transition text-sm"
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
