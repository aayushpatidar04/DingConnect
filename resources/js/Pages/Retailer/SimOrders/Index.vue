<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    orders: Object,
    stats: Object,
    filters: Object,
});

const showOrderModal = ref(false);

const orderForm = useForm({
    quantity: 50,
});

function submitOrder() {
    orderForm.post("/retailer/sim-orders", {
        preserveScroll: true,
        onSuccess: () => {
            showOrderModal.value = false;
            orderForm.reset();
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
                    Place new SIM card orders and track dispatch status and history.
                </p>
            </div>
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
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-500 uppercase tracking-wider">
                            <th class="p-4">Order ID</th>
                            <th class="p-4">Quantity</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Tracking & Notes</th>
                            <th class="p-4">Dispatched At</th>
                            <th class="p-4">Ordered At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="6" class="text-center py-10 text-ink-400">
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
                            <td class="p-4 text-xs text-ink-600 max-w-xs">
                                {{ order.tracking_notes || 'Awaiting dispatch update...' }}
                            </td>
                            <td class="p-4 text-xs text-ink-500">
                                {{ order.dispatched_at || '-' }}
                            </td>
                            <td class="p-4 text-xs text-ink-500">
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
                                    : 'text-ink-600 hover:bg-surface-3',
                                !link.url ? 'opacity-40 cursor-not-allowed' : '',
                            ]"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Place Order Modal -->
        <div
            v-if="showOrderModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900">
                        Order SIM Cards
                    </h3>
                    <button
                        @click="showOrderModal = false"
                        class="text-ink-400 hover:text-ink-900"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitOrder" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-500 uppercase mb-1">
                            Quantity of SIM Cards
                        </label>
                        <input
                            v-model.number="orderForm.quantity"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <p class="text-xs text-ink-400 mt-1">
                            Enter the number of SIM cards you wish to order (e.g. 50, 100, 500).
                        </p>
                        <InputError :message="orderForm.errors.quantity" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showOrderModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-600 hover:bg-surface-3 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="orderForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            Submit Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
