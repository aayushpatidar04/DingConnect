<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: AdminLayout });

defineProps({
    transaction: Object,
});

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

const statusClass = (status) => {
    if (status === "success") return "bg-green-200 text-green-700";
    if (status === "failed" || status === "refunded") {
        return "bg-red-500/20 text-red-600";
    }

    return "bg-yellow-500/20 text-yellow-700";
};
</script>

<template>
    <Head :title="`Transaction ${transaction.id} - Admin`" />
    <FlashMessage />

    <div class="space-y-6">
        <Link
            href="/admin/transactions"
            class="inline-flex items-center text-sm text-primary-light hover:text-primary transition"
        >
            ← Back to Transactions
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-ink-900">
                    Transaction Details
                </h1>
                <p class="mt-1 text-sm text-ink-500">
                    Transaction #{{ transaction.id }}
                    <span v-if="transaction.receipt_number">
                        · Receipt {{ transaction.receipt_number }}
                    </span>
                </p>
            </div>
            <span
                :class="[
                    'rounded-full px-3 py-1 text-sm font-medium capitalize',
                    statusClass(transaction.status),
                ]"
            >
                {{ transaction.status }}
            </span>
        </div>

        <div
            v-if="transaction.status === 'failed' && transaction.failure_reason"
            class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-600"
        >
            {{ transaction.failure_reason }}
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section
                class="space-y-4 rounded-2xl border border-surface-3 bg-surface-2 p-6"
            >
                <h2 class="text-lg font-semibold text-ink-900">
                    Recharge Details
                </h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-ink-500">Retailer</dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ transaction.user?.name || "-" }}
                        </dd>
                        <dd class="text-sm text-ink-500">
                            {{ transaction.user?.shop_name || "" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Mobile Number
                        </dt>
                        <dd class="mt-1 font-mono font-medium text-ink-900">
                            {{ transaction.mobile_number || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">Operator</dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ transaction.operator?.name || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">Country</dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ transaction.country?.name || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">Amount</dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ transaction.currency || "GBP" }}
                            {{ Number(transaction.amount).toFixed(2) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Product Type
                        </dt>
                        <dd class="mt-1 font-medium capitalize text-ink-900">
                            {{ transaction.product_type || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Redemption Type
                        </dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ transaction.redemption_type || "Immediate" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">Created</dt>
                        <dd class="mt-1 font-medium text-ink-900">
                            {{ formatDate(transaction.created_at) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="space-y-4 rounded-2xl border border-surface-3 bg-surface-2 p-6"
            >
                <h2 class="text-lg font-semibold text-ink-900">
                    Provider Details
                </h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Provider Transaction ID
                        </dt>
                        <dd class="mt-1 break-all font-mono text-sm text-ink-900">
                            {{ transaction.ding_transaction_id || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Order Reference
                        </dt>
                        <dd class="mt-1 break-all font-mono text-sm text-ink-900">
                            {{ transaction.ding_order_reference || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">SKU</dt>
                        <dd class="mt-1 font-mono text-sm text-ink-900">
                            {{ transaction.sku_code || "-" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-ink-500">
                            Callback Received
                        </dt>
                        <dd class="mt-1 text-sm text-ink-900">
                            {{
                                transaction.callback_received
                                    ? formatDate(transaction.callback_received_at)
                                    : "No"
                            }}
                        </dd>
                    </div>
                </dl>

                <div v-if="transaction.callbacks?.length">
                    <h3 class="mb-2 text-sm font-semibold text-ink-900">
                        Provider Callbacks
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="callback in transaction.callbacks"
                            :key="callback.id"
                            class="rounded-lg bg-surface-3 p-3 text-sm"
                        >
                            <div
                                class="flex flex-wrap justify-between gap-2 text-ink-900"
                            >
                                <span class="capitalize">{{ callback.status }}</span>
                                <span class="text-ink-500">
                                    {{ formatDate(callback.received_at) }}
                                </span>
                            </div>
                            <p
                                v-if="callback.error_message"
                                class="mt-1 text-red-600"
                            >
                                {{ callback.error_message }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
