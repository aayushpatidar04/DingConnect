<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputError from "@/Components/InputError.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    retailer: Object,
    activities: Object,
    operators: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const creditForm = useForm({ amount: "", description: "" });
const simLimitForm = useForm({
    sim_order_limit: props.retailer.sim_order_limit ?? 500,
});

function updateSimLimit() {
    simLimitForm.put(`/admin/sim-orders/retailers/${props.retailer.id}/limit`, {
        preserveScroll: true,
    });
}

const filters = reactive({
    search: props.filters.search ?? "",
    type: props.filters.type ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

const operatorOptions = computed(() =>
    props.operators.map((o) => ({
        id: o.id,
        label: o.name,
        hint: o.country?.iso_code,
    })),
);

const hasFilters = computed(() => Object.values(filters).some((v) => v !== ""));

const activeParams = () =>
    Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ""));

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                `/admin/retailers/${props.retailer.id}`,
                activeParams(),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);
    },
    { deep: true },
);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ""));
}

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

const activityTypeBadge = (type) => {
    switch (type) {
        case 'debit':
            return 'bg-red-500/20 text-red-600';
        case 'topup':
        case 'credit':
            return 'bg-green-200 text-green-600';
        case 'refund':
            return 'bg-yellow-500/20 text-yellow-600';
        default:
            return 'bg-surface-3 text-ink-500';
    }
};

const inputClass =
    "border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark";
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
                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
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
                            <span class="text-ink-500">Postcode:</span>
                            <p class="font-medium text-ink-900">
                                {{ retailer.postcode || "-" }}
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

                <!-- Unified Activity Filters -->
                <div class="bg-surface-2 rounded-2xl p-4 border border-surface-3 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-ink-500 block mb-1">Search</label>
                        <input
                            v-model="filters.search"
                            type="search"
                            placeholder="Mobile, receipt or description"
                            :class="[inputClass, 'w-full']"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">Type</label>
                        <select v-model="filters.type" :class="inputClass">
                            <option value="">All</option>
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                            <option value="hold">Hold</option>
                            <option value="release">Release</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">From</label>
                        <input v-model="filters.from" type="date" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1">To</label>
                        <input v-model="filters.to" type="date" :class="inputClass" />
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

                <!-- Unified Activity Table -->
                <div class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-dark-600">
                            <thead class="bg-surface-3">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Date
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Type
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Amount
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Balance Before
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Balance After
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase">
                                        Reference
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-700 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dark-600">
                                <tr
                                    v-for="activity in activities.data"
                                    :key="activity.id"
                                    class="hover:bg-surface-2 transition"
                                >
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ activity.id }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ formatDate(activity.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-ink-700">
                                        <span
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full capitalize',
                                                activityTypeBadge(activity.type),
                                            ]"
                                        >
                                            {{ activity.type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">
                                        <span
                                            :class="[
                                                activity.type === 'debit'
                                                    ? 'text-red-600'
                                                    : 'text-green-600',
                                            ]"
                                        >
                                            {{ activity.type === 'debit' ? '-' : '+' }}
                                            £ {{ Number(activity.amount).toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ activity.balance_before !== null
                                            ? '£ ' + Number(activity.balance_before).toFixed(2)
                                            : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ activity.balance_after !== null
                                            ? '£ ' + Number(activity.balance_after).toFixed(2)
                                            : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        {{ activity.reference }}
                                        {{ activity.source === 'transaction' && activity.mobile_number
                                            ? ' (' + activity.mobile_number + ')'
                                            : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            v-if="activity.has_view && activity.view_url"
                                            :href="activity.view_url"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-medium hover:bg-primary-dark transition"
                                        >
                                            View
                                        </Link>
                                        <span
                                            v-else
                                            class="px-3 py-1 rounded-lg text-xs text-ink-400"
                                        >
                                            –
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!activities.data?.length">
                                    <td
                                        colspan="8"
                                        class="px-4 py-8 text-center text-ink-500"
                                    >
                                        No wallet activity found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="activities.links" class="mt-4 flex justify-center gap-2 p-4">
                        <template v-for="link in activities.links" :key="link.label">
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

            <div class="space-y-6">
                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
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

                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900 mb-2">
                        SIM Card Order Limit
                    </h3>
                    <p class="text-xs text-ink-500 mb-4">
                        Max SIM quantity allowed in a single order for this retailer.
                    </p>
                    <form @submit.prevent="updateSimLimit" class="space-y-3">
                        <input
                            v-model.number="simLimitForm.sim_order_limit"
                            type="number"
                            min="1"
                            max="100000"
                            required
                            class="w-full px-3 py-2 border border-surface-3 rounded-lg bg-surface-3 text-ink-900 input-dark"
                        />
                        <InputError :message="simLimitForm.errors.sim_order_limit" />
                        <button
                            type="submit"
                            :disabled="simLimitForm.processing"
                            class="w-full py-2 bg-primary hover:bg-primary-hover text-ink-100 rounded-lg disabled:opacity-60 transition text-sm font-semibold shadow-md shadow-primary/20"
                        >
                            Update SIM Limit
                        </button>
                    </form>
                </div>

                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3 class="text-lg font-semibold text-ink-900 mb-4">
                        Verification Status
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
                    <p v-if="retailer.kyc_rejection_reason" class="text-sm text-red-600 mt-2">
                        {{ retailer.kyc_rejection_reason }}
                    </p>
                    <div class="mt-4 space-y-2">
                        <form :action="`/admin/retailers/${retailer.id}/kyc`" method="POST">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                            <input type="hidden" name="action" value="approve" />
                            <button type="submit" class="w-full py-2 bg-accent text-ink-100 rounded-lg hover:bg-accent-dark transition">
                                Approve
                            </button>
                        </form>
                        <form :action="`/admin/retailers/${retailer.id}/kyc`" method="POST">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                            <input type="hidden" name="action" value="reject" />
                            <input
                                type="text"
                                name="rejection_reason"
                                placeholder="Rejection reason"
                                class="w-full px-3 py-2 border border-surface-3 rounded-lg mb-2 text-sm bg-surface-3 text-ink-900 input-dark"
                            />
                            <InputError class="mt-2" :message="$page.props.errors?.rejection_reason" />
                            <button type="submit" class="w-full py-2 bg-red-600 text-ink-100 rounded-lg hover:bg-red-700 transition text-sm">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
