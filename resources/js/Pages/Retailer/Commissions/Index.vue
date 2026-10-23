<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive, watch } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import {
    money,
    amountRange,
    stepsText,
    firstTotal,
} from "@/Composables/useCommission";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    level: { type: Object, default: null },
    next: { type: Object, default: null },
    connections: Object,
    rates: { type: Array, default: () => [] },
    commissions: Object,
    totals: Object,
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    type: props.filters.type ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                "/retailer/commissions",
                Object.fromEntries(
                    Object.entries(filters).filter(([, v]) => v !== ""),
                ),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);
    },
    { deep: true },
);

const toGo = computed(() =>
    props.next
        ? Math.max(props.next.min_connections - props.connections.level, 0)
        : 0,
);

const progress = computed(() => {
    if (!props.level || !props.next) return 100;
    const span = props.next.min_connections - props.level.min_connections;
    if (span <= 0) return 100;
    const done = props.connections.level - props.level.min_connections;
    return Math.min(100, Math.max(0, Math.round((done / span) * 100)));
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
    <Head title="Commission" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Commission</h1>
            <p class="text-ink-500">
                Cashback you earn on successful recharges.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Earned today</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ money(totals.today) }}
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Earned this month</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ money(totals.month) }}
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-surface-3 p-5">
                <p class="text-sm text-ink-500">Earned in total</p>
                <p class="text-2xl font-bold text-ink-900">
                    {{ money(totals.all) }}
                </p>
            </div>
        </div>

        <div class="bg-surface-2 rounded-2xl border border-surface-3 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-ink-500">Your level</p>
                    <p class="text-2xl font-bold text-ink-900">
                        {{ level ? level.name : "No level" }}
                    </p>
                </div>
                <div class="text-sm text-ink-700 text-right">
                    <div>
                        New connections:
                        <strong class="text-ink-900">{{
                            connections.actual
                        }}</strong>
                    </div>
                    <div v-if="level && connections.auto && next">
                        {{ toGo }} more to reach {{ next.name }}
                    </div>
                </div>
            </div>
            <div v-if="level && connections.auto && next" class="mt-4">
                <div class="h-2 rounded-full bg-surface-3 overflow-hidden">
                    <div
                        class="h-full bg-primary"
                        :style="{ width: progress + '%' }"
                    ></div>
                </div>
                <div class="flex justify-between text-xs text-ink-500 mt-1">
                    <span>{{ level.name }}</span>
                    <span>{{ next.name }}</span>
                </div>
            </div>
            <p v-if="!level" class="text-sm text-ink-500 mt-3">
                You are not on a commission level at the moment. Contact
                support if you think this is wrong.
            </p>
        </div>

        <div
            v-if="level && rates.length"
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="p-4 border-b border-surface-3">
                <h2 class="text-lg font-semibold text-ink-900">
                    Your rates ({{ level.name }})
                </h2>
                <p class="text-sm text-ink-500">
                    The first top-up of a new connection pays the first total.
                    Later top-ups pay the ongoing amount.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th v-for="h in ['Operator', 'Top-up amount', 'First top-up', 'Ongoing top-ups']" :key="h" :class="thClass">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="rate in rates" :key="rate.id">
                            <td class="px-4 py-3 text-sm text-ink-900">
                                {{ rate.operator?.name ?? "All operators" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ amountRange(rate) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                <span class="font-medium text-ink-900">{{
                                    money(firstTotal(rate))
                                }}</span>
                                <span
                                    v-if="Number(rate.first_bonus_amount) > 0"
                                    class="text-xs text-ink-500"
                                >
                                    ({{ money(rate.first_topup_amount) }} +
                                    {{ money(rate.first_bonus_amount) }} bonus)</span
                                >
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ stepsText(rate) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select v-model="filters.type" :class="selectClass">
                <option value="">First + ongoing</option>
                <option value="first">First top-up</option>
                <option value="ongoing">Ongoing</option>
            </select>
            <input v-model="filters.from" type="date" :class="selectClass" />
            <input v-model="filters.to" type="date" :class="selectClass" />
        </div>

        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th v-for="h in ['Date', 'Receipt', 'Number', 'Operator', 'Type', 'Recharge', 'Commission']" :key="h" :class="thClass">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="c in commissions.data" :key="c.id">
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ formatDate(c.created_at) }}
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
                            <td class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap">
                                {{ c.type === "first" ? "First" : "Ongoing" }}
                                #{{ c.topup_sequence }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ money(c.recharge_amount) }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-ink-900">
                                {{ money(c.commission_amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!commissions.data.length"
                class="p-8 text-center text-ink-500"
            >
                No commission earned yet
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
