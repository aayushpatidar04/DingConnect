<script setup>
import { Head } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import { ref, computed, onMounted } from "vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({ transaction: Object });

const statusColors = {
    pending: "bg-yellow-500/10 text-yellow-300 border-yellow-500/30",
    processing: "bg-blue-500/10 text-blue-300 border-blue-500/30",
    success: "bg-green-500/10 text-green-300 border-green-500/30",
    failed: "bg-red-500/10 text-red-300 border-red-500/30",
    cancelled: "bg-gray-500/10 text-gray-300 border-gray-500/30",
};

const statusIcons = {
    pending: "⏳",
    processing: "🔄",
    success: "✅",
    failed: "❌",
    cancelled: "🚫",
};

const formatDate = (date) => {
    if (!date) return "-";
    return new Date(date).toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const copied = ref(false);

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        alert("Copied: " + text);
    }
};

const isReadReceipt = computed(
    () => props.transaction.redemption_type === "ReadReceipt",
);

const isImmediate = computed(
    () =>
        !props.transaction.redemption_type ||
        props.transaction.redemption_type === "Immediate",
);

const hasReceipt = computed(
    () =>
        props.transaction.status === "success" && props.transaction.receipt_text,
);

const canDownloadReceipt = computed(
    () => props.transaction.status === "success",
);

const canRetry = computed(
    () =>
        props.transaction.status === "failed" ||
        props.transaction.status === "pending",
);

const loading = ref(false);
const loadingProductDesc = ref(false);
const productDescription = ref("");

const showProductDesc = computed(
    () =>
        props.transaction.status === "success" &&
        (productDescription.value ||
            props.transaction.description_markdown ||
            props.transaction.readmore_markdown),
);

async function refreshTransaction() {
    loading.value = true;
    try {
        const res = await fetch(
            `/retailer/transactions/${props.transaction.id}/poll`,
            { headers: { "X-Requested-With": "XMLHttpRequest" } },
        );
        const data = await res.json();
        if (data.transaction) {
            Object.assign(props.transaction, data.transaction);
        }
    } catch (e) {
        console.error("Failed to refresh transaction", e);
    } finally {
        loading.value = false;
    }
}

async function fetchProductDescription() {
    const sku = props.transaction.sku_code;
    if (!sku || loadingProductDesc.value) return;

    // Skip if DB already has at least one description field
    if (
        props.transaction.description_markdown ||
        props.transaction.readmore_markdown
    )
        return;

    loadingProductDesc.value = true;
    try {
        const res = await fetch(
            `/retailer/recharge/product-description?sku_code=${sku}`,
        );
        const data = await res.json();
        if (data.success && data.description) {
            productDescription.value = data.description;
        }
    } catch (e) {
        console.error("Failed to fetch product description", e);
    } finally {
        loadingProductDesc.value = false;
    }
}

function downloadReceipt() {
    window.open(
        `/retailer/transactions/${props.transaction.id}/receipt`,
        "_blank",
    );
}

onMounted(async () => {
    // Poll for pending/processing transactions
    if (["pending", "processing"].includes(props.transaction.status)) {
        const interval = setInterval(() => {
            refreshTransaction().then(() => {
                if (
                    ["success", "failed", "cancelled"].includes(
                        props.transaction.status,
                    )
                ) {
                    clearInterval(interval);
                }
            });
        }, 3000);
        setTimeout(() => clearInterval(interval), 60000);
    }

    // Fetch product description if not already in DB
    if (props.transaction.status === "success") {
        await fetchProductDescription();
    }
});
</script>

<template>
    <Head title="Transaction - MK Network" />
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <button
                @click="$inertia.visit('/retailer/transactions')"
                class="text-sm text-dark-300 hover:text-white transition mb-3 flex items-center gap-1"
            >
                ← Back to transactions
            </button>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">
                        Transaction Details
                    </h1>
                    <p class="text-dark-300 text-sm mt-1">
                        Receipt: {{ transaction.receipt_number }}
                    </p>
                </div>
                <div
                    :class="[
                        'px-4 py-2 rounded-xl border text-sm font-semibold flex items-center gap-2',
                        statusColors[transaction.status] ||
                            'bg-gray-500/10 text-gray-300',
                    ]"
                >
                    <span>{{ statusIcons[transaction.status] || "📋" }}</span>
                    {{
                        transaction.status.charAt(0).toUpperCase() +
                        transaction.status.slice(1)
                    }}
                </div>
            </div>
        </div>

        <!-- Error / Failure -->
        <div
            v-if="transaction.status === 'failed'"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-5 mb-6"
        >
            <div class="text-red-300 font-medium">Transaction Failed</div>
            <div class="text-red-400 text-sm mt-1">
                {{ transaction.failure_reason || "Unknown error occurred" }}
            </div>
        </div>

        <!-- Main Details Card -->
        <div
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6 mb-6 space-y-5"
        >
            <!-- Plan Info -->
            <div>
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    Plan
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden"
                    >
                        <img
                            v-if="transaction.operator?.logo_url"
                            :src="transaction.operator.logo_url"
                            class="w-full h-full object-contain p-1"
                        />
                        <span v-else>📱</span>
                    </div>
                    <div>
                        <div class="font-semibold text-white">
                            {{ transaction.operator?.name || "-" }}
                        </div>
                        <div class="text-sm text-dark-300">
                            {{
                                transaction.display_text ||
                                transaction.operator?.name ||
                                "Mobile Top-Up"
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Number (Immediate only) -->
            <div v-if="isImmediate">
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    Recharged Number
                </div>
                <div class="text-white font-medium text-lg">
                    +{{ transaction.mobile_number }}
                </div>
            </div>

            <!-- ReadReceipt Note -->
            <div v-else-if="isReadReceipt">
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    Redemption Type
                </div>
                <div
                    class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-3"
                >
                    <p class="text-yellow-300 text-sm">
                        📋 <strong>PIN-based recharge</strong> — share the
                        voucher code below with your customer. Any SIM of this
                        provider can redeem it.
                    </p>
                </div>
            </div>

            <!-- Amount -->
            <div>
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    {{ isReadReceipt ? "PIN Value" : "Recharge Value" }}
                </div>
                <div class="text-2xl font-bold text-white">
                    {{ transaction.receive_currency || "GBP" }}
                    {{
                        transaction.receive_value
                            ? Number(transaction.receive_value).toFixed(2)
                            : "-"
                    }}
                </div>
            </div>

            <!-- DingConnect SKU -->
            <div
                v-if="transaction.sku_code"
                class="flex items-center justify-between"
            >
                <div class="text-xs text-dark-400 uppercase tracking-wider">
                    SKU Code
                </div>
                <div
                    class="text-xs bg-dark-700 px-2 py-1 rounded text-dark-300 font-mono"
                >
                    {{ transaction.sku_code }}
                </div>
            </div>

            <!-- Send / Receive Details -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-dark-700 rounded-xl p-4">
                    <div class="text-xs text-dark-400 mb-1">You Paid</div>
                    <div class="text-lg font-semibold text-white">
                        {{ transaction.send_currency || "GBP" }}
                        {{
                            transaction.send_value
                                ? Number(transaction.send_value).toFixed(2)
                                : "-"
                        }}
                    </div>
                </div>
                <div class="bg-dark-700 rounded-xl p-4">
                    <div class="text-xs text-dark-400 mb-1">
                        {{ isReadReceipt ? "PIN Value" : "Customer Gets" }}
                    </div>
                    <div class="text-lg font-semibold text-white">
                        {{ transaction.receive_currency || "GBP" }}
                        {{
                            transaction.receive_value
                                ? Number(transaction.receive_value).toFixed(2)
                                : "-"
                        }}
                    </div>
                </div>
            </div>

            <!-- Validity Period -->
            <div
                v-if="transaction.validity_period"
                class="flex items-center justify-between"
            >
                <div class="text-xs text-dark-400 uppercase tracking-wider">
                    Validity
                </div>
                <div class="text-sm text-green-300">
                    {{ transaction.validity_period }}
                </div>
            </div>

            <!-- Benefits -->
            <div v-if="transaction.benefits && transaction.benefits.length">
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    Benefits
                </div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="benefit in transaction.benefits"
                        :key="benefit"
                        class="text-xs bg-green-500/20 text-green-300 px-2 py-1 rounded"
                    >
                        {{ benefit }}
                    </span>
                </div>
            </div>

            <!-- Product Description -->
            <div v-if="showProductDesc">
                <div
                    class="text-xs text-dark-400 uppercase tracking-wider mb-2"
                >
                    {{ isReadReceipt ? "How to Redeem" : "Product Information" }}
                </div>
                <div
                    v-if="loadingProductDesc"
                    class="text-xs text-dark-400 animate-pulse"
                >
                    Loading product details...
                </div>
                <div
                    v-else
                    class="bg-dark-700 rounded-xl p-4 text-sm text-dark-200 prose prose-invert max-w-none"
                    v-html="
                        productDescription ||
                        transaction.readmore_markdown ||
                        transaction.description_markdown
                    "
                ></div>
            </div>

            <!-- DingConnect Reference -->
            <div
                v-if="transaction.ding_transaction_id"
                class="flex items-center justify-between"
            >
                <div class="text-xs text-dark-400 uppercase tracking-wider">
                    DingConnect ID
                </div>
                <button
                    @click="copyToClipboard(transaction.ding_transaction_id)"
                    class="text-xs bg-dark-700 px-2 py-1 rounded text-dark-300 font-mono hover:text-white transition"
                >
                    {{ transaction.ding_transaction_id }}
                    📋
                </button>
            </div>

            <!-- Order Reference -->
            <div
                v-if="transaction.ding_order_reference"
                class="flex items-center justify-between"
            >
                <div class="text-xs text-dark-400 uppercase tracking-wider">
                    Order Reference
                </div>
                <button
                    @click="copyToClipboard(transaction.ding_order_reference)"
                    class="text-xs bg-dark-700 px-2 py-1 rounded text-dark-300 font-mono hover:text-white transition"
                >
                    {{ transaction.ding_order_reference }}
                    📋
                </button>
            </div>
        </div>

        <!-- PIN / Voucher Section -->
        <div
            v-if="hasReceipt"
            class="bg-green-500/10 border border-green-500/30 rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center"
                >
                    <svg
                        class="w-5 h-5 text-green-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-green-300">
                        Recharge Successful
                    </div>
                    <div class="text-xs text-green-400">
                        {{
                            isReadReceipt
                                ? "Share this PIN with your customer to redeem the recharge"
                                : "PIN/Voucher generated for this transaction"
                        }}
                    </div>
                </div>
            </div>

            <div class="bg-dark-700/50 rounded-xl p-4 space-y-3">
                <div v-if="transaction.receipt_number">
                    <div class="text-xs text-dark-400 mb-1">Receipt Number</div>
                    <div class="text-white font-mono text-sm">
                        {{ transaction.receipt_number }}
                    </div>
                </div>

                <div>
                    <div class="text-xs text-dark-400 mb-1">
                        PIN / Voucher Code
                    </div>
                    <div
                        class="bg-dark-900 rounded-lg p-4 border border-primary/30 flex items-center justify-between"
                    >
                        <code
                            v-html="transaction.receipt_text.replace(/\n/g, '<br>')"
                            class="text-primary-light text-xl font-bold tracking-wider break-all"
                        ></code>
                        <button
                            @click="copyToClipboard(transaction.receipt_text)"
                            class="ml-3 text-xs bg-dark-700 px-3 py-1 rounded text-dark-300 hover:text-white transition whitespace-nowrap"
                        >
                            {{ copied ? "✓ Copied" : "📋 Copy" }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="isReadReceipt"
                    class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3 mt-3"
                >
                    <p class="text-yellow-300 text-xs">
                        <strong>Note:</strong> This is a ReadReceipt product.
                        Any SIM card of this operator can redeem this PIN.
                        Customer dials the operator's USSD or SMS the code to
                        activate.
                    </p>
                </div>
            </div>
        </div>

        <!-- Timestamps Card -->
        <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 mb-6">
            <div class="text-xs text-dark-400 uppercase tracking-wider mb-3">
                Timeline
            </div>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="text-sm text-dark-400 w-24">Created</div>
                    <div class="text-sm text-white">
                        {{ formatDate(transaction.created_at) }}
                    </div>
                </div>
                <div
                    v-if="transaction.callback_received_at"
                    class="flex items-center gap-3"
                >
                    <div class="text-sm text-dark-400 w-24">Updated</div>
                    <div class="text-sm text-white">
                        {{ formatDate(transaction.callback_received_at) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <button
                v-if="canRetry"
                @click="$inertia.visit('/retailer/recharge')"
                class="flex-1 py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary-dark transition"
            >
                New Recharge
            </button>
            <button
                v-if="canDownloadReceipt"
                @click="downloadReceipt"
                class="flex-1 py-3 bg-dark-800 border border-green-500/30 text-green-300 rounded-xl font-semibold hover:bg-green-500/10 transition flex items-center justify-center gap-2"
            >
                📥 Download Receipt
            </button>
        </div>
    </div>
</template>
