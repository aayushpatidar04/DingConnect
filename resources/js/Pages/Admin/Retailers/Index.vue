<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { reactive, watch, computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    retailers: Object,
    filters: { type: Object, default: () => ({}) },
});

const selectedIds = ref([]);
const selectAll = computed({
    get: () => props.retailers.data.length > 0 && selectedIds.value.length === props.retailers.data.length,
    set: (val) => {
        selectedIds.value = val ? props.retailers.data.map(r => r.id) : [];
    }
});

const showBulkModal = ref(false);
const bulkForm = useForm({
    target: "selected",
    retailer_ids: [],
    sim_order_limit: 20,
});

function openBulkModal(target) {
    bulkForm.target = target;
    bulkForm.retailer_ids = selectedIds.value;
    bulkForm.sim_order_limit = 20;
    bulkForm.clearErrors();
    showBulkModal.value = true;
}

function submitBulk() {
    bulkForm.retailer_ids = selectedIds.value;
    bulkForm.post("/admin/retailers/bulk-sim-limit", {
        preserveScroll: true,
        onSuccess: () => {
            showBulkModal.value = false;
            selectedIds.value = [];
        },
    });
}

const filters = reactive({
    search: props.filters.search ?? "",
    status: props.filters.status ?? "active",
    kyc_status: props.filters.kyc_status ?? "",
});

const hasFilters = computed(() => Object.values(filters).some((v) => v !== ""));

const activeParams = () =>
    Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ""));

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get("/admin/retailers", activeParams(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 400);
    },
    { deep: true },
);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ""));
}

const exportRetailers = () => {
    const qs = new URLSearchParams(activeParams()).toString();
    window.location.href =
        route("admin.retailers.export") + (qs ? "?" + qs : "");
};

const inputClass =
    "border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark";
</script>

<template>
    <Head title="Retailers - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">Retailers</h1>
                <p class="text-ink-500">Manage registered retailers and SIM order limits</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    @click="openBulkModal('all')"
                    class="px-4 py-2 bg-primary/15 text-primary hover:bg-primary hover:text-ink-100 rounded-lg text-sm font-semibold transition"
                >
                    Set SIM Limit for ALL Retailers
                </button>
                <button
                    @click="exportRetailers"
                    class="px-4 py-2 bg-accent text-ink-100 rounded-lg hover:bg-accent-dark text-sm font-medium transition"
                >
                    Export CSV
                </button>
                <Link
                    href="/admin/retailers/create"
                    class="px-4 py-2 btn-primary text-ink-100 rounded-lg text-sm font-medium"
                    >Add Retailer</Link
                >
            </div>
        </div>

        <!-- Selected Action Bar -->
        <div v-if="selectedIds.length > 0" class="bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
            <span class="text-sm font-semibold text-primary">{{ selectedIds.length }} retailer(s) selected</span>
            <button
                @click="openBulkModal('selected')"
                class="px-4 py-2 bg-primary text-ink-100 rounded-xl text-xs font-semibold hover:bg-primary-hover transition shadow-md shadow-primary/20"
            >
                Set SIM Limit for Selected
            </button>
        </div>

        <div class="flex gap-2 mb-4">
            <button
                @click="filters.status = 'active'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition"
                :class="
                    filters.status === 'active'
                        ? 'bg-primary text-white'
                        : 'bg-surface-2 text-ink-500'
                "
            >
                Active Retailers
            </button>

            <button
                @click="filters.status = 'inactive'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition"
                :class="
                    filters.status === 'inactive'
                        ? 'bg-primary text-white'
                        : 'bg-surface-2 text-ink-500'
                "
            >
                Inactive Retailers
            </button>
        </div>

        <!-- Filters -->
        <div
            class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end"
        >
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs text-ink-500 block mb-1">Search</label>
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Name, email, phone, postcode or shop name"
                    :class="[inputClass, 'w-full']"
                />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1"
                    >Verification</label
                >
                <select v-model="filters.kyc_status" :class="inputClass">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <button
                v-if="hasFilters"
                type="button"
                @click="reset"
                class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
            >
                Reset
            </button>
        </div>

        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-600">
                    <thead class="bg-surface-3">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    v-model="selectAll"
                                    class="rounded border-surface-3 text-primary focus:ring-primary"
                                />
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                S.No.
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Retailer
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Shop
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Level
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Postcode
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Wallet
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Verification
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="(retailer, index) in retailers.data"
                            :key="retailer.id"
                            class="hover:bg-surface-2 transition"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    :value="retailer.id"
                                    v-model="selectedIds"
                                    class="rounded border-surface-3 text-primary focus:ring-primary"
                                />
                            </td>
                            <td class="px-4 py-4 text-sm text-ink-700">
                                {{ (retailers.from || 0) + index }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-ink-900">
                                    {{ retailer.name }}
                                </div>
                                <div class="text-sm text-ink-500">
                                    {{ retailer.email }}
                                </div>
                                <div class="text-sm text-ink-500">
                                    {{ retailer.phone }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-ink-700">
                                {{ retailer.shop_name || "-" }}
                            </td>
                            <td class="px-6 py-4 text-sm text-ink-700">
                                {{
                                    retailer.retailer_commission_profile?.level
                                        ?.name || "-"
                                }}
                            </td>
                            <td class="px-6 py-4 text-sm text-ink-700">
                                {{ retailer.postcode || "-" }}
                            </td>
                            <td
                                class="px-6 py-4 text-sm font-medium text-ink-900"
                            >
                                £
                                {{
                                    Number(
                                        retailer.wallet?.balance || 0,
                                    ).toFixed(2)
                                }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="{
                                        'bg-yellow-500/20 text-yellow-600':
                                            retailer.kyc_status === 'pending',
                                        'bg-green-200 text-green-600':
                                            retailer.kyc_status === 'approved',
                                        'bg-red-500/20 text-red-600':
                                            retailer.kyc_status === 'rejected',
                                    }"
                                    >{{ retailer.kyc_status }}</span
                                >
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="`/admin/retailers/${retailer.id}`"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-medium hover:bg-primary-dark transition"
                                    >
                                        View
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!retailers.data?.length">
                            <td
                                colspan="9"
                                class="px-6 py-8 text-center text-ink-500"
                            >
                                No retailers found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="retailers.links" class="p-4 flex justify-center gap-2">
                <template v-for="link in retailers.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm border border-surface-3 transition"
                        :class="
                            link.active
                                ? 'bg-primary text-ink-100 border-primary'
                                : 'bg-surface-2 text-ink-500 hover:bg-surface-2'
                        "
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500"
                    />
                </template>
            </div>
        </div>

        <!-- Bulk SIM Limit Modal -->
        <div
            v-if="showBulkModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        >
            <div class="bg-surface-2 border border-surface-3 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900">
                        {{ bulkForm.target === 'all' ? 'Set SIM Limit for ALL Retailers' : `Set SIM Limit for ${selectedIds.length} Selected Retailer(s)` }}
                    </h3>
                    <button
                        @click="showBulkModal = false"
                        class="text-ink-400 hover:text-ink-900"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitBulk" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            New Max SIM Quantity Per Single Order
                        </label>
                        <input
                            v-model.number="bulkForm.sim_order_limit"
                            type="number"
                            min="1"
                            max="100000"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <p class="text-xs text-ink-500 mt-1">
                            This will update the maximum SIM card order limit for {{ bulkForm.target === 'all' ? 'all registered retailers' : `${selectedIds.length} selected retailer(s)` }}.
                        </p>
                        <InputError :message="bulkForm.errors.sim_order_limit" class="mt-1" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showBulkModal = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-ink-700 hover:bg-surface-3 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="bulkForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-xl text-sm font-medium transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            Apply Bulk Limit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
