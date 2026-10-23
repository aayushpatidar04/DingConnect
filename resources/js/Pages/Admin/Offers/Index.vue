<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";
import MultiPicker from "@/Components/MultiPicker.vue";
import CommissionModal from "@/Components/CommissionModal.vue";
import { money } from "@/Composables/useCommission";
import {
    benefitLabel,
    offerRange,
    formatOfferDate,
    offerStatusStyles,
} from "@/Composables/useOffers";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    offers: { type: Array, default: () => [] },
    operators: { type: Array, default: () => [] },
    retailers: { type: Array, default: () => [] },
});

const statusFilter = ref("");

const shown = computed(() =>
    statusFilter.value
        ? props.offers.filter((o) => o.status === statusFilter.value)
        : props.offers,
);

/* ---------- offer form ---------- */
const showModal = ref(false);

const form = useForm({
    id: null,
    title: "",
    description: "",
    type: "percent",
    value: "",
    max_cashback: "",
    operator_ids: [],
    min_amount: 0,
    max_amount: "",
    starts_at: "",
    expires_at: "",
    per_retailer_limit: "",
    total_limit: "",
    audience: "all",
    retailer_ids: [],
    is_active: true,
});

function open(offer = null) {
    form.clearErrors();
    form.id = offer?.id ?? null;
    form.title = offer?.title ?? "";
    form.description = offer?.description ?? "";
    form.type = offer?.type ?? "percent";
    form.value = offer?.value ?? "";
    form.max_cashback = offer?.max_cashback ?? "";
    form.operator_ids = offer ? [...offer.operator_ids] : [];
    form.min_amount = offer?.min_amount ?? 0;
    form.max_amount = offer?.max_amount ?? "";
    form.starts_at = offer?.starts_at ?? "";
    form.expires_at = offer?.expires_at ?? "";
    form.per_retailer_limit = offer?.per_retailer_limit ?? "";
    form.total_limit = offer?.total_limit ?? "";
    form.audience = offer?.audience ?? "all";
    form.retailer_ids = offer ? [...offer.retailer_ids] : [];
    form.is_active = offer?.is_active ?? true;
    showModal.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };
    if (form.id) {
        form.put(`/admin/offers/${form.id}`, options);
    } else {
        form.post("/admin/offers", options);
    }
}

function remove(offer) {
    if (!confirm(`Delete "${offer.title}"?`)) return;
    router.delete(`/admin/offers/${offer.id}`, { preserveScroll: true });
}

function togglePause(offer) {
    router.put(
        `/admin/offers/${offer.id}`,
        {
            title: offer.title,
            description: offer.description,
            type: offer.type,
            value: offer.value,
            max_cashback: offer.max_cashback,
            operator_ids: offer.operator_ids,
            min_amount: offer.min_amount,
            max_amount: offer.max_amount,
            starts_at: offer.starts_at,
            expires_at: offer.expires_at,
            per_retailer_limit: offer.per_retailer_limit,
            total_limit: offer.total_limit,
            audience: offer.audience,
            retailer_ids: offer.retailer_ids,
            is_active: !offer.is_active,
        },
        { preserveScroll: true },
    );
}

const summary = computed(() => {
    if (!form.value) return "";
    const what =
        form.type === "percent"
            ? `${form.value}% cashback${form.max_cashback ? ` (up to ${money(form.max_cashback)})` : ""}`
            : `${money(form.value)} cashback`;
    const providers = form.operator_ids.length
        ? `${form.operator_ids.length} selected provider(s)`
        : "all providers";
    return `${what} on ${offerRange(form).toLowerCase()} recharges for ${providers}`;
});

/* ---------- redemptions ---------- */
const showUses = ref(false);
const usesOffer = ref(null);
const uses = ref([]);
const usesLoading = ref(false);

async function openUses(offer) {
    usesOffer.value = offer;
    uses.value = [];
    usesLoading.value = true;
    showUses.value = true;
    try {
        const res = await window.axios.get(
            `/admin/offers/${offer.id}/redemptions`,
        );
        uses.value = res.data.redemptions;
    } finally {
        usesLoading.value = false;
    }
}

const inputClass =
    "w-full border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none";
const labelClass = "block text-xs font-medium text-ink-500 mb-1";
const thClass =
    "px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap";
</script>

<template>
    <Head title="Offers - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">Offers</h1>
                <p class="text-ink-500">
                    Cashback offers retailers can choose on a recharge. Only
                    one offer can be used per recharge.
                </p>
            </div>
            <button
                type="button"
                @click="open()"
                class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
            >
                + New offer
            </button>
        </div>

        <div class="flex gap-3">
            <select
                v-model="statusFilter"
                class="border border-surface-3 rounded-lg px-4 py-2 bg-surface-3 text-ink-900 text-sm outline-none"
            >
                <option value="">All offers</option>
                <option value="active">Active</option>
                <option value="scheduled">Scheduled</option>
                <option value="paused">Paused</option>
                <option value="exhausted">Used up</option>
                <option value="expired">Expired</option>
            </select>
        </div>

        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th v-for="h in ['Offer', 'Benefit', 'Providers', 'Recharge amount', 'Valid until', 'Who', 'Used', 'Status', '']" :key="h" :class="thClass">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="o in shown" :key="o.id">
                            <td class="px-4 py-3 text-sm text-ink-900 font-medium">
                                {{ o.title }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ benefitLabel(o) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 max-w-[180px]">
                                {{ o.operator_names.length ? o.operator_names.join(", ") : "All providers" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ offerRange(o) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ formatOfferDate(o.expires_at_iso) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ o.audience === "all" ? "All retailers" : `${o.retailer_ids.length} retailer(s)` }}
                                <div class="text-[11px] text-ink-500">
                                    {{ o.per_retailer_limit ? `${o.per_retailer_limit} per retailer` : "unlimited per retailer" }}
                                    <template v-if="o.total_limit"> · {{ o.total_limit }} total</template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="openUses(o)"
                                    class="text-primary-light hover:text-primary"
                                >
                                    {{ o.used }} · {{ money(o.paid) }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs capitalize"
                                    :class="offerStatusStyles[o.status]"
                                    >{{ o.status === "exhausted" ? "used up" : o.status }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <button type="button" @click="open(o)" class="text-primary-light hover:text-primary mr-3">Edit</button>
                                <button
                                    v-if="o.status !== 'expired'"
                                    type="button"
                                    @click="togglePause(o)"
                                    class="text-ink-500 hover:text-ink-900 mr-3"
                                >
                                    {{ o.is_active ? "Pause" : "Resume" }}
                                </button>
                                <button type="button" @click="remove(o)" class="text-red-600 hover:text-red-500">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!shown.length" class="p-8 text-center text-ink-500">
                {{ offers.length ? "No offers with this status" : "No offers yet. Create the first one." }}
            </div>
        </div>
    </div>

    <!-- Offer form -->
    <CommissionModal
        :show="showModal"
        :title="form.id ? 'Edit offer' : 'New offer'"
        max-width="max-w-2xl"
        @close="showModal = false"
    >
        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label :class="labelClass">Title (retailers see this)</label>
                <input v-model="form.title" type="text" :class="inputClass" placeholder="e.g. Vodafone Diwali cashback" />
                <InputError :message="form.errors.title" />
            </div>
            <div>
                <label :class="labelClass">Description (optional)</label>
                <textarea v-model="form.description" rows="2" :class="inputClass"></textarea>
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label :class="labelClass">Cashback type</label>
                    <select v-model="form.type" :class="inputClass">
                        <option value="percent">Percentage of the recharge</option>
                        <option value="fixed">Fixed amount (£)</option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass">{{ form.type === "percent" ? "Percentage (%)" : "Amount (£)" }}</label>
                    <input v-model="form.value" type="number" step="0.01" min="0" :class="inputClass" />
                    <InputError :message="form.errors.value" />
                </div>
                <div v-if="form.type === 'percent'">
                    <label :class="labelClass">Max cashback (£, optional)</label>
                    <input v-model="form.max_cashback" type="number" step="0.01" min="0" :class="inputClass" />
                    <InputError :message="form.errors.max_cashback" />
                </div>
            </div>

            <div>
                <label :class="labelClass">Providers (leave empty for all providers)</label>
                <MultiPicker
                    v-model="form.operator_ids"
                    :options="operators"
                    placeholder="Search providers..."
                    empty-text="All providers"
                />
                <InputError :message="form.errors.operator_ids" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Recharge amount from (£)</label>
                    <input v-model="form.min_amount" type="number" step="0.01" min="0" :class="inputClass" />
                    <InputError :message="form.errors.min_amount" />
                </div>
                <div>
                    <label :class="labelClass">Up to (£) – empty for no limit</label>
                    <input v-model="form.max_amount" type="number" step="0.01" min="0" :class="inputClass" />
                    <InputError :message="form.errors.max_amount" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Starts (optional, UK time)</label>
                    <input v-model="form.starts_at" type="datetime-local" :class="inputClass" />
                    <InputError :message="form.errors.starts_at" />
                </div>
                <div>
                    <label :class="labelClass">Expires (UK time)</label>
                    <input v-model="form.expires_at" type="datetime-local" :class="inputClass" />
                    <InputError :message="form.errors.expires_at" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Uses per retailer (empty = unlimited)</label>
                    <input v-model="form.per_retailer_limit" type="number" min="1" :class="inputClass" />
                    <InputError :message="form.errors.per_retailer_limit" />
                </div>
                <div>
                    <label :class="labelClass">Total uses, all retailers (empty = unlimited)</label>
                    <input v-model="form.total_limit" type="number" min="1" :class="inputClass" />
                    <InputError :message="form.errors.total_limit" />
                </div>
            </div>

            <div>
                <label :class="labelClass">Who can see and use it</label>
                <div class="flex gap-6 text-sm text-ink-700 mb-2">
                    <label class="flex items-center gap-2">
                        <input v-model="form.audience" type="radio" value="all" /> All retailers
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.audience" type="radio" value="selected" /> Selected retailers only
                    </label>
                </div>
                <MultiPicker
                    v-if="form.audience === 'selected'"
                    v-model="form.retailer_ids"
                    :options="retailers"
                    placeholder="Search retailers..."
                    empty-text="No retailers selected"
                />
                <InputError :message="form.errors.retailer_ids" />
            </div>

            <div
                v-if="summary"
                class="rounded-xl bg-surface-3 px-4 py-3 text-sm text-ink-700"
            >
                {{ summary }}
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input v-model="form.is_active" type="checkbox" class="rounded border-surface-3" />
                Active (untick to keep it hidden)
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showModal = false" class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3">Cancel</button>
                <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60">Save offer</button>
            </div>
        </form>
    </CommissionModal>

    <!-- Uses -->
    <CommissionModal
        :show="showUses"
        :title="`Uses – ${usesOffer?.title ?? ''}`"
        max-width="max-w-3xl"
        @close="showUses = false"
    >
        <div v-if="usesLoading" class="text-ink-500 text-sm">Loading…</div>
        <div v-else-if="!uses.length" class="text-ink-500 text-sm">This offer has not been used yet.</div>
        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-surface-3 text-sm">
                <thead>
                    <tr>
                        <th v-for="h in ['Date', 'Retailer', 'Number', 'Provider', 'Recharge', 'Cashback', 'Status']" :key="h" :class="thClass">{{ h }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-3">
                    <tr v-for="u in uses" :key="u.id">
                        <td class="px-4 py-2 text-ink-700 whitespace-nowrap">{{ formatOfferDate(u.at) }}</td>
                        <td class="px-4 py-2 text-ink-900">{{ u.retailer }}</td>
                        <td class="px-4 py-2 text-ink-700">{{ u.number }}</td>
                        <td class="px-4 py-2 text-ink-700">{{ u.operator }}</td>
                        <td class="px-4 py-2 text-ink-700">{{ money(u.recharge_amount) }}</td>
                        <td class="px-4 py-2 text-ink-900 font-medium">{{ money(u.benefit_amount) }}</td>
                        <td class="px-4 py-2 capitalize text-ink-700">{{ u.status }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CommissionModal>
</template>
