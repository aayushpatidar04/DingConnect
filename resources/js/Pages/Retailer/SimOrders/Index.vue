<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    orders: Object,
    stats: Object,
    simProducts: { type: Array, default: () => [] },
    simOrderLimit: { type: Number, default: 500 },
});

const showOrderModal = ref(false);

const quantities = ref({});
props.simProducts.forEach((product) => {
    quantities.value[product.id] = 0;
});

const totalOrderedQuantity = computed(() => {
    return Object.values(quantities.value).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
});

const estimatedTotalPrice = computed(() => {
    return props.simProducts.reduce((total, product) => {
        return total + Number(product.price) * Number(quantities.value[product.id] || 0);
    }, 0);
});

const productsByProvider = computed(() => {
    const groups = new Map();

    props.simProducts.forEach((product) => {
        if (!groups.has(product.provider_name)) {
            groups.set(product.provider_name, []);
        }

        groups.get(product.provider_name).push(product);
    });

    return Array.from(groups, ([providerName, products]) => ({ providerName, products }));
});

const orderForm = useForm({
    items: [],
});

function submitOrder() {
    orderForm.items = props.simProducts
        .map((product) => ({
            sim_order_product_id: product.id,
            quantity: Number(quantities.value[product.id] || 0),
        }))
        .filter((item) => item.quantity > 0);

    orderForm.post("/retailer/sim-orders", {
        preserveScroll: true,
        onSuccess: () => {
            showOrderModal.value = false;
            props.simProducts.forEach((product) => {
                quantities.value[product.id] = 0;
            });
        },
    });
}

function formatCategory(category) {
    return category.endsWith("GBP") ? `£${category.slice(0, -3)}` : category;
}

function formatPrice(price) {
    return Number(price).toFixed(2);
}

const statusBadge = (status) => {
    switch (status) {
        case "pending":
            return "bg-amber-500/10 text-amber-600 border-amber-500/20";
        case "processing":
            return "bg-blue-500/10 text-blue-600 border-blue-500/20";
        case "dispatched":
            return "bg-purple-500/10 text-purple-600 border-purple-500/20";
        case "delivered":
            return "bg-emerald-500/10 text-emerald-600 border-emerald-500/20";
        case "rejected":
            return "bg-red-500/10 text-red-600 border-red-500/20";
        default:
            return "bg-surface-3 text-ink-600 border-surface-4";
    }
};
</script>

<template>
    <Head title="SIM Card Orders - Retailer Portal" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">
                    SIM Card Orders & Tracking
                </h1>
                <p class="text-ink-500">
                    Place itemized SIM card orders by provider and category, and track dispatch status and history.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-xs bg-surface-2 border border-surface-3 px-3 py-2 rounded-xl text-ink-700 font-medium">
                    Max Order Limit: <strong class="text-primary">{{ simOrderLimit }} SIMs</strong>
                </span>
                <button
                    @click="showOrderModal = true"
                    class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-ink-100 font-medium rounded-xl transition shadow-lg shadow-primary/20 flex items-center justify-center space-x-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Order SIM Cards</span>
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-ink-500">Total Orders</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ stats.total }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-amber-600">Pending Orders</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">
                    {{ stats.pending }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-purple-600">Dispatched</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">
                    {{ stats.dispatched }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-emerald-600">Delivered</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">
                    {{ stats.delivered }}
                </p>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">Order ID</th>
                            <th class="p-4">Itemized Breakdown</th>
                            <th class="p-4">Total Qty</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Tracking & Notes</th>
                            <th class="p-4">Dispatched At</th>
                            <th class="p-4">Ordered At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="7" class="text-center py-10 text-ink-500 font-medium">
                                You haven't placed any SIM card orders yet. Click "Order SIM Cards" to get started.
                            </td>
                        </tr>
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="hover:bg-surface-3/30 transition"
                        >
                            <td class="p-4 font-semibold text-primary">
                                #{{ order.id }}
                            </td>
                            <td class="p-4">
                                <div class="space-y-1">
                                    <div
                                        v-for="(item, idx) in order.items"
                                        :key="idx"
                                        class="text-xs inline-flex items-center bg-surface-3 px-2 py-1 rounded-md mr-1 mb-1 font-medium text-ink-800"
                                    >
                                        <span class="font-bold mr-1">{{ item.provider_name }}</span>
                                        <span class="text-primary bg-primary/10 px-1.5 py-0.5 rounded mr-1">{{ formatCategory(item.category) }}</span>
                                        <span class="font-semibold">({{ item.quantity }})</span>
                                        <span v-if="item.unit_price !== null" class="ml-1 text-ink-500">@ £{{ formatPrice(item.unit_price) }}</span>
                                    </div>
                                    <span v-if="!order.items || order.items.length === 0" class="text-xs text-ink-400">–</span>
                                </div>
                            </td>
                            <td class="p-4 font-bold">
                                {{ order.quantity }} SIMs
                            </td>
                            <td class="p-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border capitalize"
                                    :class="statusBadge(order.status)"
                                >
                                    {{ order.status }}
                                </span>
                            </td>
                            <td class="p-4 text-xs text-ink-700 max-w-xs">
                                {{ order.tracking_notes || 'Awaiting dispatch update...' }}
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ order.dispatched_at || '-' }}
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ order.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="orders.links && orders.links.length > 3"
                class="p-4 border-t border-surface-3 flex items-center justify-between"
            >
                <div class="text-xs text-ink-500">
                    Showing {{ orders.from }} to {{ orders.to }} of {{ orders.total }} results
                </div>
                <div class="flex space-x-1">
                    <template v-for="(link, i) in orders.links" :key="i">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-xs font-medium border border-surface-3 transition"
                            :class="[
                                link.active
                                    ? 'bg-primary text-ink-100 border-primary'
                                    : 'text-ink-700 hover:bg-surface-3',
                                !link.url ? 'opacity-40 cursor-not-allowed' : '',
                            ]"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Place Order Modal (Itemized by Provider & Category) -->
        <div
            v-if="showOrderModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-2xl p-6 shadow-2xl space-y-4 my-8">
                <div class="flex items-center justify-between border-b border-surface-3 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-ink-900">
                            Order SIM Cards by Provider & Category
                        </h3>
                        <p class="text-xs text-ink-500">
                            Specify quantity for each provider and SIM tier. Total ordered must not exceed your account limit of <strong>{{ simOrderLimit }}</strong>.
                        </p>
                    </div>
                    <button
                        @click="showOrderModal = false"
                        class="text-ink-400 hover:text-ink-900 text-lg font-bold"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitOrder" class="space-y-4">
                    <div class="max-h-[60vh] overflow-y-auto pr-2 space-y-4">
                        <div
                            v-for="group in productsByProvider"
                            :key="group.providerName"
                            class="bg-surface-3/40 border border-surface-3 rounded-xl p-4 space-y-3"
                        >
                            <h4 class="font-bold text-ink-900 text-sm flex items-center justify-between">
                                <span>{{ group.providerName }}</span>
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div
                                    v-for="product in group.products"
                                    :key="product.id"
                                    class="flex items-center justify-between gap-3 rounded-lg bg-surface-2 border border-surface-3 p-3"
                                >
                                    <div>
                                        <label :for="`sim-product-${product.id}`" class="block text-sm font-semibold text-ink-900">
                                            {{ formatCategory(product.category) }} SIM
                                        </label>
                                        <span class="text-xs text-ink-500">Price: £{{ formatPrice(product.price) }}</span>
                                    </div>
                                    <input
                                        :id="`sim-product-${product.id}`"
                                        v-model.number="quantities[product.id]"
                                        type="number"
                                        min="0"
                                        max="10000"
                                        class="w-24 border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-2 text-ink-900 focus:outline-none focus:border-primary text-center font-bold"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-3 pt-4 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-ink-500 block">Total Selected SIMs:</span>
                            <span
                                class="text-lg font-bold"
                                :class="totalOrderedQuantity > simOrderLimit ? 'text-red-600' : 'text-primary'"
                            >
                                {{ totalOrderedQuantity }} / {{ simOrderLimit }}
                            </span>
                            <span class="block text-xs text-ink-500">
                                Estimated total: <strong class="text-ink-800">£{{ formatPrice(estimatedTotalPrice) }}</strong>
                            </span>
                        </div>
                        <InputError :message="orderForm.errors.items" />

                        <div class="flex space-x-3">
                            <button
                                type="button"
                                @click="showOrderModal = false"
                                class="px-4 py-2 rounded-xl text-sm font-medium text-ink-700 hover:bg-surface-3 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="orderForm.processing || totalOrderedQuantity <= 0 || totalOrderedQuantity > simOrderLimit"
                                class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                            >
                                Submit Order
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
