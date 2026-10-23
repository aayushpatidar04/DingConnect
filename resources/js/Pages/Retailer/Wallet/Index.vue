<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { reactive, ref, watch, computed } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    wallet: Object,
    availableBalance: Number,
    topups: Object,
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    status: props.filters.status ?? "",
    type: props.filters.type ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

const hasFilters = computed(() => Object.values(filters).some((v) => v !== ""));

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const params = Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v !== ""),
            );
            router.get("/retailer/wallet", params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ""));
}

const inputClass =
    "border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark";

const money = (v) => `£ ${Number(v ?? 0).toFixed(2)}`;

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

/* ---------- what each kind of entry looks like ---------- */
const kinds = {
    card: {
        label: "Card top-up",
        badge: "bg-blue-500/15 text-blue-600",
        icon: "M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z",
    },
    bank_transfer: {
        label: "Bank transfer",
        badge: "bg-blue-500/15 text-blue-600",
        icon: "M3 10l9-6 9 6M5 10v8m4-8v8m6-8v8m4-8v8M3 21h18",
    },
    commission: {
        label: "Commission",
        badge: "bg-green-500/15 text-green-600",
        icon: "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
    },
    bonus: {
        label: "Bonus",
        badge: "bg-purple-500/15 text-purple-600",
        icon: "M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7",
    },
    offer: {
        label: "Offer cashback",
        badge: "bg-orange-500/15 text-orange-600",
        icon: "M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z",
    },
    admin: {
        label: "Admin credit",
        badge: "bg-surface-3 text-ink-700",
        icon: "M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z",
    },
};

const statusStyles = {
    completed: "bg-green-200 text-green-600",
    failed: "bg-red-500/20 text-red-600",
    cancelled: "bg-surface-3 text-ink-500",
    processing: "bg-blue-500/20 text-blue-600",
    pending: "bg-yellow-500/20 text-yellow-600",
};

const statusHelp = {
    pending: "Waiting for the payment to be confirmed.",
    processing: "The payment is being processed.",
    failed: "The payment did not go through. No money was added.",
    cancelled: "This top-up was cancelled. No money was added.",
};

const position = (d) =>
    d?.type === "first" ? "First top-up" : d?.sequence ? `Top-up #${d.sequence}` : null;

function title(topup) {
    const kind = kinds[topup.kind]?.label ?? "Top-up";
    const d = topup.details;
    if (topup.kind === "commission" || topup.kind === "bonus") {
        return [kind, position(d)].filter(Boolean).join(" · ");
    }
    if (topup.kind === "offer") {
        return [kind, d?.offer_title].filter(Boolean).join(" · ");
    }
    return kind;
}

const isCredit = (topup) => topup.status === "completed";

/* ---------- expandable details ---------- */
const open = reactive({});

const toggle = (id) => (open[id] = !open[id]);

const copied = ref(null);

async function copy(text, key) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 1500);
    } catch (e) {
        // clipboard not available, ignore
    }
}

function rows(topup) {
    const d = topup.details ?? {};
    const list = [
        { label: "Type", value: kinds[topup.kind]?.label },
        { label: "Status", value: topup.status, capitalize: true },
        { label: "Amount added", value: money(topup.amount) },
    ];

    if (topup.kind === "card" || topup.kind === "bank_transfer") {
        list.push({
            label: "Fee",
            value:
                topup.fee_amount > 0
                    ? `${money(topup.fee_amount)} (${Number(topup.fee_percentage).toFixed(2)}%)`
                    : "None",
        });
        list.push({ label: "Total charged", value: money(topup.total_charged) });
    }

    if (topup.receipt_number) {
        list.push({ label: "Receipt number", value: topup.receipt_number, copy: true });
    }
    if (topup.reference) {
        list.push({ label: "Payment reference", value: topup.reference, copy: true, mono: true });
    }

    if (d.operator) list.push({ label: "Provider", value: d.operator });
    if (d.number) list.push({ label: "Mobile number", value: d.number });
    if (d.offer_title) list.push({ label: "Offer", value: d.offer_title });
    if (position(d)) list.push({ label: "Recharge position", value: position(d) });
    if (d.level) list.push({ label: "Commission level", value: d.level });
    if (d.description) list.push({ label: "Note", value: d.description, wide: true });
    if (d.recharge_id) {
        list.push({
            label: "Recharge",
            value: d.recharge_receipt ?? `#${d.recharge_id}`,
            href: `/retailer/transactions/${d.recharge_id}`,
        });
    }

    list.push({ label: "Created", value: formatDate(topup.created_at) });
    if (topup.status === "completed" && topup.updated_at !== topup.created_at) {
        list.push({ label: "Completed", value: formatDate(topup.updated_at) });
    }

    return list;
}
</script>

<template>
    <Head title="Wallet - MK All Network Communications" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">My Wallet</h1>
            <p class="text-ink-500">
                Manage your wallet balance and top-up history
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="stat-gradient-1 rounded-2xl p-6 card-hover">
                <div class="text-sm text-blue-100">Wallet Balance</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    £ {{ Number(wallet.balance).toFixed(2) }}
                </div>
            </div>
            <div class="stat-gradient-2 rounded-2xl p-6 card-hover">
                <div class="text-sm text-green-100">Available Balance</div>
                <div class="text-3xl font-bold text-ink-100 mt-1">
                    £ {{ Number(availableBalance).toFixed(2) }}
                </div>
            </div>
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 flex items-center justify-between"
            >
                <div>
                    <div class="text-sm text-ink-500">Need More Balance?</div>
                    <div class="text-sm text-ink-500 mt-1">
                        Top up your wallet instantly
                    </div>
                </div>
                <Link
                    href="/retailer/wallet/topup"
                    class="btn-primary px-6 py-3 bg-primary text-ink-100 rounded-xl font-medium"
                    >Top Up</Link
                >
            </div>
        </div>

        <!-- Where the money came from (completed entries, all time) -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <div
                v-for="card in [
                    { label: 'Added by you', value: summary.deposits },
                    { label: 'Fees paid', value: summary.fees },
                    { label: 'Commission', value: summary.commission },
                    { label: 'Bonus', value: summary.bonus },
                    { label: 'Offer cashback', value: summary.offers },
                    { label: 'Admin credits', value: summary.admin },
                ]"
                :key="card.label"
                class="bg-surface-2 rounded-xl border border-surface-3 px-4 py-3"
            >
                <div class="text-xs text-ink-500">{{ card.label }}</div>
                <div class="text-lg font-semibold text-ink-900">
                    {{ money(card.value) }}
                </div>
            </div>
        </div>

        <!-- Top Up History -->
        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div
                class="p-6 border-b border-surface-3 flex flex-wrap items-end justify-between gap-4"
            >
                <h3 class="text-lg font-semibold text-ink-900">
                    Top-Up History
                </h3>

                <!-- Filters -->
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="text-xs text-ink-500 block mb-1"
                            >Type</label
                        >
                        <select v-model="filters.type" :class="inputClass">
                            <option value="">All types</option>
                            <option value="card">Card top-up</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="commission">Commission</option>
                            <option value="bonus">Bonus</option>
                            <option value="offer">Offer cashback</option>
                            <option value="admin">Admin credit</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1"
                            >Status</label
                        >
                        <select v-model="filters.status" :class="inputClass">
                            <option value="">All Status</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1"
                            >From</label
                        >
                        <input
                            v-model="filters.from"
                            type="date"
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-ink-500 block mb-1"
                            >To</label
                        >
                        <input
                            v-model="filters.to"
                            type="date"
                            :class="inputClass"
                        />
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
            </div>

            <div v-if="topups.data?.length" class="divide-y divide-surface-3">
                <div v-for="topup in topups.data" :key="topup.id">
                    <!-- Summary row -->
                    <button
                        type="button"
                        @click="toggle(topup.id)"
                        class="w-full text-left p-4 flex items-center gap-4 hover:bg-surface-3/50 transition"
                    >
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                            :class="kinds[topup.kind]?.badge"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    :d="kinds[topup.kind]?.icon"
                                />
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-ink-900 truncate">
                                {{ title(topup) }}
                            </div>
                            <div class="text-sm text-ink-500">
                                {{ formatDate(topup.created_at) }}
                                <template v-if="topup.receipt_number">
                                    · {{ topup.receipt_number }}
                                </template>
                            </div>
                            <div
                                v-if="topup.details?.operator || topup.details?.number"
                                class="flex flex-wrap gap-1.5 mt-1"
                            >
                                <span
                                    v-if="topup.details.operator"
                                    class="px-2 py-0.5 rounded-lg text-xs bg-surface-3 text-ink-700"
                                    >{{ topup.details.operator }}</span
                                >
                                <span
                                    v-if="topup.details.number"
                                    class="px-2 py-0.5 rounded-lg text-xs bg-surface-3 text-ink-700"
                                    >{{ topup.details.number }}</span
                                >
                            </div>
                            <div
                                v-else-if="topup.details?.description"
                                class="text-xs text-ink-500 mt-0.5 truncate"
                            >
                                {{ topup.details.description }}
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <div
                                class="font-semibold"
                                :class="
                                    isCredit(topup)
                                        ? 'text-green-600'
                                        : 'text-ink-500'
                                "
                            >
                                {{ isCredit(topup) ? "+" : "" }}{{ money(topup.amount) }}
                            </div>
                            <div
                                v-if="topup.fee_amount > 0"
                                class="text-xs text-ink-500"
                            >
                                fee {{ money(topup.fee_amount) }}
                            </div>
                            <span
                                :class="[
                                    'inline-block mt-1 px-3 py-0.5 text-xs rounded-full font-medium capitalize',
                                    statusStyles[topup.status] ??
                                        'bg-yellow-500/20 text-yellow-600',
                                ]"
                            >
                                {{ topup.status }}
                            </span>
                        </div>

                        <svg
                            class="w-4 h-4 text-ink-500 flex-shrink-0 transition-transform"
                            :class="open[topup.id] ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <!-- Details -->
                    <div
                        v-if="open[topup.id]"
                        class="px-4 pb-4 pl-[4.5rem] bg-surface-3/30"
                    >
                        <dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-3 pt-3">
                            <div
                                v-for="row in rows(topup)"
                                :key="row.label"
                                :class="row.wide ? 'sm:col-span-2 xl:col-span-3' : ''"
                            >
                                <dt class="text-xs text-ink-500">
                                    {{ row.label }}
                                </dt>
                                <dd
                                    class="text-sm text-ink-900 break-all flex items-center gap-2"
                                    :class="[
                                        row.capitalize ? 'capitalize' : '',
                                        row.mono ? 'font-mono text-xs' : '',
                                    ]"
                                >
                                    <Link
                                        v-if="row.href"
                                        :href="row.href"
                                        class="text-primary-light hover:text-primary"
                                        >{{ row.value }} &rarr;</Link
                                    >
                                    <span v-else>{{ row.value }}</span>
                                    <button
                                        v-if="row.copy"
                                        type="button"
                                        @click.stop="copy(row.value, `${topup.id}-${row.label}`)"
                                        class="text-xs text-ink-500 hover:text-ink-900 whitespace-nowrap"
                                    >
                                        {{
                                            copied === `${topup.id}-${row.label}`
                                                ? "Copied"
                                                : "Copy"
                                        }}
                                    </button>
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="statusHelp[topup.status]"
                            class="text-xs text-ink-500 mt-3"
                        >
                            {{ statusHelp[topup.status] }}
                        </p>
                    </div>
                </div>
            </div>
            <div v-else class="p-8 text-center text-ink-500">
                {{
                    hasFilters
                        ? "No top-ups match these filters"
                        : "No top-ups yet"
                }}
            </div>

            <!-- Pagination -->
            <div
                v-if="topups.total > 0"
                class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-surface-3"
            >
                <p class="text-xs text-ink-500">
                    Showing {{ topups.from }}–{{ topups.to }} of
                    {{ topups.total }}
                </p>

                <div
                    v-if="topups.last_page > 1"
                    class="flex flex-wrap justify-center gap-2"
                >
                    <template v-for="link in topups.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-sm border border-surface-3 transition"
                            :class="
                                link.active
                                    ? 'bg-primary text-ink-100 border-primary'
                                    : 'bg-surface-2 text-ink-500 hover:bg-surface-3'
                            "
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500 opacity-50"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
