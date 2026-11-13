<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    orders: Object,
    stats: Object,
    filters: Object,
    monthlyData: Array,
    simProducts: { type: Array, default: () => [] },
});

const filters = reactive({
    search: props.filters.search ?? "",
    status: props.filters.status ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const params = Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v !== "")
            );
            router.get("/admin/sim-orders", params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 400);
    },
    { deep: true }
);

function resetFilters() {
    filters.search = "";
    filters.status = "";
    filters.from = "";
    filters.to = "";
}

// Status modal management
const showUpdateModal = ref(false);
const activeOrder = ref(null);

const updateForm = useForm({
    status: "pending",
    tracking_notes: "",
});

function openUpdateModal(order) {
    activeOrder.value = order;
    updateForm.status = order.status;
    updateForm.tracking_notes = order.tracking_notes ?? "";
    updateForm.clearErrors();
    showUpdateModal.value = true;
}

function submitUpdate() {
    updateForm.put(`/admin/sim-orders/${activeOrder.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showUpdateModal.value = false;
            activeOrder.value = null;
        },
    });
}

// Retailer Limit Modal management
const showLimitModal = ref(false);
const activeRetailer = ref(null);

const limitForm = useForm({
    sim_order_limit: 500,
});

function openLimitModal(retailer) {
    if (!retailer) return;
    activeRetailer.value = retailer;
    limitForm.sim_order_limit = retailer.sim_order_limit ?? 500;
    limitForm.clearErrors();
    showLimitModal.value = true;
}

function submitLimit() {
    limitForm.put(`/admin/sim-orders/retailer-limit/${activeRetailer.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showLimitModal.value = false;
            activeRetailer.value = null;
        },
    });
}

const priceForm = useForm({
    products: props.simProducts.map((product) => ({
        id: product.id,
        provider_name: product.provider_name,
        category: product.category,
        price: Number(product.price),
    })),
});

const productsByProvider = computed(() => {
    const groups = new Map();

    priceForm.products.forEach((product, index) => {
        if (!groups.has(product.provider_name)) {
            groups.set(product.provider_name, []);
        }

        groups.get(product.provider_name).push({ ...product, index });
    });

    return Array.from(groups, ([providerName, products]) => ({ providerName, products }));
});

function submitProductPrices() {
    priceForm.put("/admin/sim-orders/product-prices", {
        preserveScroll: true,
    });
}

const showAddProductModal = ref(false);
const addProductForm = useForm({
    provider_name: "",
    category: "",
    price: 10.00,
});

function submitNewProduct() {
    addProductForm.post("/admin/sim-orders/products", {
        preserveScroll: true,
        onSuccess: () => {
            showAddProductModal.value = false;
            addProductForm.reset();
        },
    });
}

function deleteProduct(productId) {
    if (confirm("Are you sure you want to delete this SIM product?")) {
        router.delete(`/admin/sim-orders/products/${productId}`, {
            preserveScroll: true,
        });
    }
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

const maxDailyQty = computed(() =>
    Math.max(...(props.monthlyData || []).map((d) => d.total_quantity), 1)
);

const chartPoints = computed(() => {
    const data = props.monthlyData || [];
    if (data.length === 0) return [];
    const width = 800;
    const height = 200;
    const padding = 30;
    const usableWidth = width - padding * 2;
    const usableHeight = height - padding * 2;
    const max = maxDailyQty.value;

    return data.map((item, index) => {
        const x = data.length === 1 ? width / 2 : padding + (index / (data.length - 1)) * usableWidth;
        const y = height - padding - (item.total_quantity / max) * usableHeight;
        return {
            x,
            y,
            ...item,
        };
    });
});

const svgPath = computed(() => {
    const pts = chartPoints.value;
    if (pts.length === 0) return "";
    if (pts.length === 1) return `M ${pts[0].x} ${pts[0].y}`;
    
    let path = `M ${pts[0].x} ${pts[0].y}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i];
        const p1 = pts[i + 1];
        const midX = (p0.x + p1.x) / 2;
        path += ` Q ${p0.x} ${p0.y}, ${midX} ${(p0.y + p1.y) / 2} T ${p1.x} ${p1.y}`;
    }
    return path;
});

const svgAreaPath = computed(() => {
    const pts = chartPoints.value;
    if (pts.length === 0) return "";
    const first = pts[0];
    const last = pts[pts.length - 1];
    const line = svgPath.value;
    return `${line} L ${last.x} 200 L ${first.x} 200 Z`;
});

const hoveredPoint = ref(null);
</script>

<template>
    <Head title="SIM Card Orders - Admin Panel" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">
                SIM Card Orders & Retailer Limits
            </h1>
            <p class="text-ink-500">
                Review and manage itemized retailer SIM card orders, dispatch/rejection status, and individual order limits.
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">Total Orders</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ stats.total }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Pending</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">
                    {{ stats.pending }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Processing</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">
                    {{ stats.processing }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">Dispatched</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">
                    {{ stats.dispatched }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Delivered</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">
                    {{ stats.delivered }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary">Month SIM Qty</p>
                <p class="text-2xl font-bold text-primary mt-1">
                    {{ stats.this_month_quantity }}
                </p>
            </div>
        </div>

        <!-- Analytics Area & Line Chart Card -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-2">
                <div>
                    <h3 class="text-lg font-bold text-ink-900">
                        SIM Ordering Analytics (Last 30 Days)
                    </h3>
                    <p class="text-xs text-ink-500 mt-0.5">
                        Interactive trend of requested SIM card volumes over time
                    </p>
                </div>
                <!-- Fixed height status box to prevent any layout shifting -->
                <div class="text-xs bg-primary/10 text-primary px-3 py-1.5 rounded-xl font-semibold border border-primary/20 h-8 flex items-center min-w-[220px] justify-center">
                    <span v-if="hoveredPoint">
                        {{ hoveredPoint.date }}: <strong class="text-ink-900">{{ hoveredPoint.total_quantity }} SIMs</strong> ({{ hoveredPoint.total_orders }} ord)
                    </span>
                    <span v-else class="text-ink-500 italic">
                        Hover over any point on graph
                    </span>
                </div>
            </div>

            <div v-if="monthlyData.length === 0" class="text-center py-12 text-ink-400">
                No orders recorded in the last 30 days.
            </div>
            <div v-else class="relative pt-4 pb-2">
                <svg viewBox="0 0 800 200" class="w-full h-64 overflow-visible">
                    <defs>
                        <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-primary, #6366f1)" stop-opacity="0.35" />
                            <stop offset="100%" stop-color="var(--color-primary, #6366f1)" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>

                    <!-- Horizontal Grid lines -->
                    <line x1="30" y1="30" x2="770" y2="30" stroke="currentColor" class="text-surface-3" stroke-dasharray="4 4" stroke-width="1" />
                    <line x1="30" y1="100" x2="770" y2="100" stroke="currentColor" class="text-surface-3" stroke-dasharray="4 4" stroke-width="1" />
                    <line x1="30" y1="170" x2="770" y2="170" stroke="currentColor" class="text-surface-3" stroke-dasharray="4 4" stroke-width="1" />

                    <!-- Area Fill -->
                    <path :d="svgAreaPath" fill="url(#chartGradient)" />

                    <!-- Smooth Curve Line -->
                    <path :d="svgPath" fill="none" stroke="var(--color-primary, #6366f1)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />

                    <!-- Data Points -->
                    <g v-for="pt in chartPoints" :key="pt.date">
                        <circle
                            :cx="pt.x"
                            :cy="pt.y"
                            :r="hoveredPoint && hoveredPoint.date === pt.date ? 8 : 5"
                            :class="hoveredPoint && hoveredPoint.date === pt.date ? 'fill-primary stroke-surface-1' : 'fill-surface-2 stroke-primary'"
                            class="transition-all duration-150 cursor-pointer shadow"
                            stroke-width="3"
                            @mouseenter="hoveredPoint = pt"
                            @mouseleave="hoveredPoint = null"
                        />
                    </g>
                </svg>

                <!-- X Axis Date Labels -->
                <div class="flex justify-between items-center text-[11px] text-ink-500 font-medium px-4 mt-2">
                    <span v-if="monthlyData.length > 0">{{ monthlyData[0].date }}</span>
                    <span v-if="monthlyData.length > 2">{{ monthlyData[Math.floor(monthlyData.length / 2)].date }}</span>
                    <span v-if="monthlyData.length > 1">{{ monthlyData[monthlyData.length - 1].date }}</span>
                </div>
            </div>
        </div>

        <!-- Editable SIM product prices -->
        <section class="bg-surface-2 border border-surface-3 rounded-2xl p-6 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-ink-900">SIM & Top-up Product Prices</h3>
                    <p class="text-sm text-ink-500 mt-1">
                        Update product prices or add new SIM products dynamically. Retailers will see them immediately.
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        type="button"
                        @click="showAddProductModal = true"
                        class="px-4 py-2.5 bg-surface-3 hover:bg-surface-4 text-ink-900 font-medium rounded-xl transition border border-surface-4 text-sm flex items-center space-x-1.5"
                    >
                        <span>+ Add SIM Product</span>
                    </button>
                    <button
                        type="button"
                        @click="submitProductPrices"
                        :disabled="priceForm.processing || simProducts.length === 0"
                        class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-ink-100 font-medium rounded-xl transition shadow-lg shadow-primary/20 disabled:opacity-50 text-sm"
                    >
                        {{ priceForm.processing ? "Saving..." : "Save Prices" }}
                    </button>
                </div>
            </div>

            <InputError :message="priceForm.errors.products" />

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <div
                    v-for="group in productsByProvider"
                    :key="group.providerName"
                    class="rounded-xl border border-surface-3 bg-surface-3/30 p-4 space-y-3"
                >
                    <h4 class="font-bold text-ink-900">{{ group.providerName }}</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            v-for="product in group.products"
                            :key="product.id"
                            class="flex items-center justify-between gap-3 rounded-lg bg-surface-2 border border-surface-3 p-3"
                        >
                            <div class="flex items-center justify-between space-x-2">
                                <label :for="`sim-product-price-${product.id}`" class="text-sm font-medium text-ink-700">
                                    {{ formatCategory(product.category) }} SIM
                                </label>
                                <button
                                    type="button"
                                    @click="deleteProduct(product.id)"
                                    class="text-xs text-red-500 hover:text-red-700 p-1"
                                    title="Delete product"
                                >
                                    ✕
                                </button>
                            </div>
                            <div class="w-32">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-500">£</span>
                                    <input
                                        :id="`sim-product-price-${product.id}`"
                                        v-model.number="priceForm.products[product.index].price"
                                        type="number"
                                        min="0"
                                        max="100000"
                                        step="0.01"
                                        required
                                        class="w-full border border-surface-3 rounded-lg pl-7 pr-3 py-2 text-sm bg-surface-2 text-ink-900 focus:outline-none focus:border-primary text-right font-semibold"
                                    />
                                </div>
                                <InputError :message="priceForm.errors[`products.${product.index}.price`]" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex flex-wrap gap-3 items-center flex-1">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search retailer, shop name, email..."
                    class="border border-surface-3 rounded-xl px-4 py-2 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary w-full sm:w-72"
                />
                <select
                    v-model="filters.status"
                    class="border border-surface-3 rounded-xl px-4 py-2 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="delivered">Delivered</option>
                    <option value="rejected">Rejected</option>
                </select>
                <input
                    v-model="filters.from"
                    type="date"
                    class="border border-surface-3 rounded-xl px-3 py-2 text-sm bg-surface-3 text-ink-900"
                />
                <input
                    v-model="filters.to"
                    type="date"
                    class="border border-surface-3 rounded-xl px-3 py-2 text-sm bg-surface-3 text-ink-900"
                />
            </div>
            <button
                @click="resetFilters"
                class="px-4 py-2 text-sm text-ink-600 hover:text-ink-900 font-medium transition"
            >
                Reset Filters
            </button>
        </div>

        <!-- Orders Table -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">ID</th>
                            <th class="p-4">Retailer / Shop</th>
                            <th class="p-4">Itemized Breakdown</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Tracking & Notes</th>
                            <th class="p-4">Ordered At</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="7" class="text-center py-10 text-ink-500 font-medium">
                                No SIM orders found.
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
                                <div class="font-medium text-ink-900">
                                    {{ order.retailer?.shop_name || order.retailer?.name }}
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ order.retailer?.email }} | {{ order.retailer?.phone }}
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-ink-900 mb-1">{{ order.quantity }} Total SIMs</div>
                                <div class="space-y-1">
                                    <div
                                        v-for="(item, idx) in order.items"
                                        :key="idx"
                                        class="text-xs inline-flex items-center bg-surface-3 px-2 py-0.5 rounded mr-1 mb-1 font-medium text-ink-800"
                                    >
                                        <span class="font-bold mr-1">{{ item.provider_name }}</span>
                                        <span class="text-primary bg-primary/10 px-1 py-0.5 rounded mr-1">{{ formatCategory(item.category) }}</span>
                                        <span>({{ item.quantity }})</span>
                                        <span v-if="item.unit_price !== null" class="ml-1 text-ink-500">@ £{{ formatPrice(item.unit_price) }}</span>
                                    </div>
                                    <span v-if="!order.items || order.items.length === 0" class="text-xs text-ink-400">–</span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border capitalize"
                                    :class="statusBadge(order.status)"
                                >
                                    {{ order.status }}
                                </span>
                            </td>
                            <td class="p-4 text-xs text-ink-700 max-w-xs truncate">
                                {{ order.tracking_notes || '-' }}
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ order.created_at }}
                            </td>
                            <td class="p-4 text-right space-x-2 whitespace-nowrap">
                                <button
                                    @click="openLimitModal(order.retailer)"
                                    class="px-3 py-1.5 bg-surface-3 hover:bg-surface-4 text-ink-800 rounded-xl text-xs font-medium transition"
                                    title="Edit Retailer SIM Order Limit"
                                >
                                    Set Limit
                                </button>
                                <button
                                    @click="openUpdateModal(order)"
                                    class="px-3 py-1.5 bg-primary/15 text-primary hover:bg-primary hover:text-ink-100 rounded-xl text-xs font-medium transition"
                                >
                                    Update Status
                                </button>
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

        <!-- Update Status Modal -->
        <div
            v-if="showUpdateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900">
                        Update SIM Order #{{ activeOrder?.id }}
                    </h3>
                    <button
                        @click="showUpdateModal = false"
                        class="text-ink-400 hover:text-ink-900"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitUpdate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Status
                        </label>
                        <select
                            v-model="updateForm.status"
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="delivered">Delivered</option>
                            <option value="rejected">Rejected (Shortage / Other)</option>
                        </select>
                        <InputError :message="updateForm.errors.status" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Tracking Notes / Rejection Reason
                        </label>
                        <textarea
                            v-model="updateForm.tracking_notes"
                            rows="3"
                            placeholder="Enter courier info, tracking number, or reason for rejection..."
                            class="w-full border border-surface-3 rounded-xl p-3 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        ></textarea>
                        <InputError :message="updateForm.errors.tracking_notes" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showUpdateModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-700 hover:bg-surface-3 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="updateForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            Save & Notify Retailer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Set Retailer SIM Limit Modal -->
        <div
            v-if="showLimitModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900">
                        Set SIM Order Limit for {{ activeRetailer?.shop_name || activeRetailer?.name }}
                    </h3>
                    <button
                        @click="showLimitModal = false"
                        class="text-ink-400 hover:text-ink-900"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitLimit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Max SIM Quantity Per Single Order
                        </label>
                        <input
                            v-model.number="limitForm.sim_order_limit"
                            type="number"
                            min="1"
                            max="100000"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <p class="text-xs text-ink-500 mt-1">
                            Retailer will be restricted from placing single orders exceeding this quantity.
                        </p>
                        <InputError :message="limitForm.errors.sim_order_limit" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showLimitModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-700 hover:bg-surface-3 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="limitForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            Update Limit
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add SIM Product Modal -->
        <div
            v-if="showAddProductModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900">
                        Add New SIM Product
                    </h3>
                    <button
                        @click="showAddProductModal = false"
                        class="text-ink-400 hover:text-ink-900"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitNewProduct" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Provider Name
                        </label>
                        <input
                            v-model="addProductForm.provider_name"
                            type="text"
                            placeholder="e.g. EE, Vodafone, Lebara, Lyca Mobile"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <InputError :message="addProductForm.errors.provider_name" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Category / Tier
                        </label>
                        <input
                            v-model="addProductForm.category"
                            type="text"
                            placeholder="e.g. 10GBP, 50GBP, Standard"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <InputError :message="addProductForm.errors.category" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Price (£)
                        </label>
                        <input
                            v-model.number="addProductForm.price"
                            type="number"
                            min="0"
                            max="100000"
                            step="0.01"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary font-semibold"
                        />
                        <InputError :message="addProductForm.errors.price" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showAddProductModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-700 hover:bg-surface-3 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="addProductForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
