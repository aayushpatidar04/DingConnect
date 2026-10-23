<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import CommissionTabs from "@/Components/CommissionTabs.vue";
import { money, skipReasons } from "@/Composables/useCommission";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    commissions: Object,
    stats: Object,
    filters: { type: Object, default: () => ({}) },
    retailers: { type: Array, default: () => [] },
    operators: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
});

const keys = [
    "search",
    "status",
    "type",
    "retailer_id",
    "operator_id",
    "level_id",
    "from",
    "to",
];

const filters = reactive(
    Object.fromEntries(keys.map((k) => [k, props.filters[k] ?? ""])),
);

const activeParams = () =>
    Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ""));

const hasFilters = computed(() => Object.keys(activeParams()).length > 0);

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get("/admin/commissions", activeParams(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 400);
    },
    { deep: true },
);

function reset() {
    keys.forEach((k) => (filters[k] = ""));
}

const exportUrl = computed(() => {
    const qs = new URLSearchParams(activeParams()).toString();
    return `/admin/commissions/export${qs ? `?${qs}` : ""}`;
});

const formatDate = (date) =>
    date
        ? new Date(date).toLocaleString("en-GB", {
              timeZone: "Europe/London",
              day: "2-digit",
              month: "short",
              year: "numeric",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "-";

const selectClass =
    "border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm outline-none";
const thClass =
    "px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap";
</script>

<template>
    <Head title="Commission Report - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Commission</h1>
            <p class="text-ink-500">
                Every successful recharge that was checked for cashback.
            </p>
        </div>

        <CommissionTabs />

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Commission paid</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ money(stats.total_paid) }}
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Credited recharges</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ stats.credited }}
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Skipped (no commission)</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ stats.skipped }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Mobile or receipt..."
                class="border border-surface-3 rounded-lg px-4 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
            />
            <select v-model="filters.retailer_id" :class="selectClass">
                <option value="">All retailers</option>
                <option v-for="r in retailers" :key="r.id" :value="r.id">
                    {{ r.shop_name || r.name }}
                </option>
            </select>
            <select v-model="filters.operator_id" :class="selectClass">
                <option value="">All operators</option>
                <option v-for="o in operators" :key="o.id" :value="o.id">
                    {{ o.name }}
                </option>
            </select>
            <select v-model="filters.level_id" :class="selectClass">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l.id" :value="l.id">
                    {{ l.name }}
                </option>
            </select>
            <select v-model="filters.type" :class="selectClass">
                <option value="">First + ongoing</option>
                <option value="first">First top-up</option>
                <option value="ongoing">Ongoing</option>
            </select>
            <select v-model="filters.status" :class="selectClass">
                <option value="">All statuses</option>
                <option value="credited">Credited</option>
                <option value="skipped">Skipped</option>
            </select>
            <input v-model="filters.from" type="date" :class="selectClass" />
            <input v-model="filters.to" type="date" :class="selectClass" />
            <button
                v-if="hasFilters"
                type="button"
                @click="reset"
                class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-500 hover:bg-surface-3 transition"
            >
                Reset
            </button>
            <a
                :href="exportUrl"
                class="px-4 py-2 bg-green-600 text-ink-100 rounded-lg text-sm font-medium hover:bg-green-700 transition ml-auto"
                >Export Excel</a
            >
        </div>

        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th v-for="h in ['Date', 'Retailer', 'Receipt', 'Number', 'Operator', 'Level', 'Type', 'Recharge', 'Commission', 'Status']" :key="h" :class="thClass">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="c in commissions.data" :key="c.id">
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ formatDate(c.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-900">
                                {{ c.user?.shop_name || c.user?.name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ c.transaction?.receipt_number ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ c.transaction?.mobile_number ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ c.operator?.name ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ c.level?.name ?? "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                <template v-if="c.type"
                                    >{{ c.type === "first" ? "First" : "Ongoing" }}
                                    #{{ c.topup_sequence }}</template
                                >
                                <template v-else>-</template>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ money(c.recharge_amount) }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-ink-900">
                                <template v-if="c.status === 'credited'">
                                    {{ money(c.commission_amount) }}
                                    <div
                                        v-if="Number(c.bonus_amount) > 0"
                                        class="text-[11px] font-normal text-ink-500"
                                    >
                                        {{ money(c.topup_amount) }} +
                                        {{ money(c.bonus_amount) }} bonus
                                    </div>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs"
                                    :class="
                                        c.status === 'credited'
                                            ? 'bg-green-500/20 text-green-600'
                                            : 'bg-surface-3 text-ink-500'
                                    "
                                    >{{
                                        c.status === "credited"
                                            ? "Credited"
                                            : "Skipped"
                                    }}</span
                                >
                                <div
                                    v-if="c.skip_reason"
                                    class="text-[11px] text-ink-500 mt-0.5"
                                >
                                    {{ skipReasons[c.skip_reason] ?? c.skip_reason }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!commissions.data.length"
                class="p-8 text-center text-ink-500"
            >
                {{ hasFilters ? "Nothing matches these filters" : "No commissions yet" }}
            </div>
            <div
                v-if="commissions.links"
                class="flex justify-center gap-2 p-4 flex-wrap"
            >
                <template v-for="link in commissions.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
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
                        class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
