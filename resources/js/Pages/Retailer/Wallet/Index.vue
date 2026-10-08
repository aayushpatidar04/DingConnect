<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { reactive, watch, computed } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    wallet: Object,
    availableBalance: Number,
    topups: Object,
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    status: props.filters.status ?? "",
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
</script>

<template>
    <Head title="Wallet - MK Network" />
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
                            >Status</label
                        >
                        <select v-model="filters.status" :class="inputClass">
                            <option value="">All Status</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
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
                <div
                    v-for="topup in topups.data"
                    :key="topup.id"
                    class="p-4 flex items-center justify-between hover:bg-surface-3/50 transition"
                >
                    <div>
                        <div class="font-medium text-ink-900">
                            £ {{ Number(topup.amount).toFixed(2) }}
                        </div>
                        <div class="text-sm text-ink-500">
                            {{ topup.payment_method }} ·
                            {{ formatDate(topup.created_at) }}
                        </div>
                    </div>
                    <span
                        :class="[
                            'px-3 py-1 text-xs rounded-full font-medium',
                            topup.status === 'completed'
                                ? 'bg-green-200 text-green-600'
                                : topup.status === 'failed'
                                  ? 'bg-red-500/20 text-red-600'
                                  : 'bg-yellow-500/20 text-yellow-600',
                        ]"
                    >
                        {{ topup.status }}
                    </span>
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