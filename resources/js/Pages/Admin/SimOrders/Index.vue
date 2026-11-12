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
        default:
            return "bg-surface-3 text-ink-600 border-surface-4";
    }
};

const maxDailyQty = computed(() =>
    Math.max(...(props.monthlyData || []).map((d) => d.total_quantity), 1)
);
</script>

<template>
    <Head title="SIM Card Orders - Admin Panel" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">
                SIM Card Orders Management
            </h1>
            <p class="text-ink-500">
                Review and manage retailer SIM card orders, dispatch status, and tracking.
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-ink-500">Total Orders</p>
                <p class="text-2xl font-bold text-ink-900 mt-1">
                    {{ stats.total }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-amber-600">Pending</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">
                    {{ stats.pending }}
                </p>
            </div>
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-blue-600">Processing</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">
                    {{ stats.processing }}
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
            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-4">
                <p class="text-sm font-medium text-primary">Month SIM Qty</p>
                <p class="text-2xl font-bold text-primary mt-1">
                    {{ stats.this_month_quantity }}
                </p>
            </div>
        </div>

        <!-- Analytics Chart Card -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-ink-900 mb-4">
                SIM Ordering Analytics (Last 30 Days)
            </h3>
            <div v-if="monthlyData.length === 0" class="text-center py-8 text-ink-400">
                No orders recorded in the last 30 days.
            </div>
            <div v-else class="h-48 flex items-end gap-2 pt-6 pb-2 px-2 overflow-x-auto">
                <div
                    v-for="item in monthlyData"
                    :key="item.date"
                    class="flex-1 flex flex-col items-center group relative min-w-[28px]"
                >
                    <!-- Tooltip -->
                    <div
                        class="absolute bottom-full mb-2 hidden group-hover:flex flex-col items-center bg-surface-900 text-ink-100 text-xs rounded-lg py-1 px-2 z-10 whitespace-nowrap shadow-lg"
                    >
                        <span class="font-bold">{{ item.date }}</span>
                        <span>{{ item.total_quantity }} SIMs ({{ item.total_orders }} orders)</span>
                    </div>
                    <div
                        class="w-full bg-primary/80 hover:bg-primary rounded-t transition-all duration-200"
                        :style="{
                            height: Math.max(8, (item.total_quantity / maxDailyQty) * 100) + '%',
                        }"
                    ></div>
                    <span class="text-[10px] text-ink-400 mt-2 rotate-[-45deg] sm:rotate-0 truncate">
                        {{ item.date.slice(5) }}
                    </span>
                </div>
            </div>
        </div>

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
                class="px-4 py-2 text-sm text-ink-500 hover:text-ink-900 transition"
            >
                Reset Filters
            </button>
        </div>

        <!-- Orders Table -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-500 uppercase tracking-wider">
                            <th class="p-4">ID</th>
                            <th class="p-4">Retailer / Shop</th>
                            <th class="p-4">Quantity</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Tracking & Notes</th>
                            <th class="p-4">Ordered At</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="7" class="text-center py-8 text-ink-400">
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
                                <div class="font-medium">
                                    {{ order.retailer?.shop_name || order.retailer?.name }}
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ order.retailer?.email }} | {{ order.retailer?.phone }}
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
                            <td class="p-4 text-xs text-ink-600 max-w-xs truncate">
                                {{ order.tracking_notes || '-' }}
                            </td>
                            <td class="p-4 text-xs text-ink-500">
                                {{ order.created_at }}
                            </td>
                            <td class="p-4 text-right">
                                <button
                                    @click="openUpdateModal(order)"
                                    class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-ink-100 rounded-xl text-xs font-medium transition"
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
                                    : 'text-ink-600 hover:bg-surface-3',
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
                        <label class="block text-xs font-semibold text-ink-500 uppercase mb-1">
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
                        </select>
                        <InputError :message="updateForm.errors.status" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-500 uppercase mb-1">
                            Tracking Notes / Courier Info
                        </label>
                        <textarea
                            v-model="updateForm.tracking_notes"
                            rows="3"
                            placeholder="Enter courier name, tracking number, or dispatch notes..."
                            class="w-full border border-surface-3 rounded-xl p-3 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        ></textarea>
                        <InputError :message="updateForm.errors.tracking_notes" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showUpdateModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-600 hover:bg-surface-3 transition"
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
    </div>
</template>
