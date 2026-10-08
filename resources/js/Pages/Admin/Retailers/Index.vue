<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { reactive, watch, computed } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    retailers: Object,
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters.search ?? "",
    status: props.filters.status ?? "",
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
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">Retailers</h1>
                <p class="text-ink-500">Manage registered retailers</p>
            </div>
            <div class="flex gap-2">
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

        <!-- Filters -->
        <div
            class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end"
        >
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs text-ink-500 block mb-1">Search</label>
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Name, email, phone or shop"
                    :class="[inputClass, 'w-full']"
                />
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">Status</label>
                <select v-model="filters.status" :class="inputClass">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-ink-500 block mb-1">KYC</label>
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
                                Wallet
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                KYC
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider"
                            >
                                Status
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
                            v-for="retailer in retailers.data"
                            :key="retailer.id"
                            class="hover:bg-surface-2 transition"
                        >
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
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="
                                        retailer.is_active
                                            ? 'bg-green-200 text-green-600'
                                            : 'bg-red-500/20 text-red-600'
                                    "
                                >
                                    {{
                                        retailer.is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <Link
                                    :href="`/admin/retailers/${retailer.id}`"
                                    class="text-primary-light hover:text-primary transition mr-2"
                                    >View</Link
                                >
                                <form
                                    :action="`/admin/retailers/${retailer.id}/approve`"
                                    method="POST"
                                    class="inline"
                                    v-if="retailer.kyc_status === 'pending'"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        :value="$page.props.csrf_token"
                                    />
                                    <button
                                        type="submit"
                                        class="text-accent-light hover:text-accent transition"
                                    >
                                        Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!retailers.data?.length"
                class="p-8 text-center text-ink-500"
            >
                {{ hasFilters ? "No retailers match these filters" : "No retailers found" }}
            </div>
            <div
                v-if="retailers.links"
                class="mt-4 flex justify-center gap-2 p-4"
            >
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
    </div>
</template>
