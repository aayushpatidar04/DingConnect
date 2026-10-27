<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";

const props = defineProps({
    networks: { type: Array, default: () => [] },
    isRetailer: Boolean,
});

const layoutComponent = computed(() =>
    props.isRetailer ? RetailerLayout : AppLayout,
);

/* ---------- state ---------- */
const search = ref("");
const country = ref("");
const sortBy = ref("name");
const expanded = reactive({}); // networks showing all of their plans
const failedLogos = reactive({}); // logos that could not be loaded

const PLANS_PREVIEW = 4;

/* ---------- derived data ---------- */
const countryKey = (n) => n.country_iso || n.country_name || "";

const countries = computed(() => {
    const map = new Map();
    props.networks.forEach((n) => {
        const key = countryKey(n);
        if (!key) return;
        const entry = map.get(key) ?? {
            key,
            name: n.country_name || n.country_iso,
            iso: n.country_iso,
            count: 0,
        };
        entry.count += 1;
        map.set(key, entry);
    });
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
});

// plans of every network, cheapest first
const sortedPlans = computed(
    () =>
        new Map(
            props.networks.map((n) => [
                n.id,
                [...(n.plans ?? [])].sort((a, b) => a.price - b.price),
            ]),
        ),
);

const plansOf = (network) => sortedPlans.value.get(network.id) ?? [];

const lowestPrice = (network) => {
    const plans = plansOf(network);
    return plans.length ? plans[0].price : null;
};

const visiblePlans = (network) =>
    expanded[network.id]
        ? plansOf(network)
        : plansOf(network).slice(0, PLANS_PREVIEW);

const hiddenCount = (network) =>
    Math.max(plansOf(network).length - PLANS_PREVIEW, 0);

const visibleNetworks = computed(() => {
    const term = search.value.trim().toLowerCase();

    const list = props.networks.filter((n) => {
        if (country.value && countryKey(n) !== country.value) return false;
        if (!term) return true;
        return [n.name, n.country_name, n.country_iso]
            .filter(Boolean)
            .join(" ")
            .toLowerCase()
            .includes(term);
    });

    return list.sort((a, b) => {
        if (sortBy.value === "price") {
            return (lowestPrice(a) ?? Infinity) - (lowestPrice(b) ?? Infinity);
        }
        if (sortBy.value === "plans") {
            return plansOf(b).length - plansOf(a).length;
        }
        return a.name.localeCompare(b.name);
    });
});

const hasFilters = computed(
    () => search.value.trim() !== "" || country.value !== "",
);

function clearFilters() {
    search.value = "";
    country.value = "";
}

/* ---------- small helpers ---------- */
function initials(name) {
    return (name ?? "")
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
}

function flag(iso) {
    if (!iso || iso.length !== 2) return "";
    return iso
        .toUpperCase()
        .replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0)));
}

const price = (value) => Number(value).toFixed(2);

/* ---------- hero numbers count up on load ---------- */
const stats = computed(() => ({
    networks: props.networks.length,
    plans: props.networks.reduce((sum, n) => sum + (n.plans?.length ?? 0), 0),
    countries: countries.value.length,
}));

const shown = reactive({ networks: 0, plans: 0, countries: 0 });
let frame = null;

onMounted(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        Object.assign(shown, stats.value);
        return;
    }

    const start = performance.now();
    const duration = 1100;

    const tick = (now) => {
        const t = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - t, 3);
        Object.keys(shown).forEach((k) => {
            shown[k] = Math.round(stats.value[k] * eased);
        });
        if (t < 1) frame = requestAnimationFrame(tick);
    };

    frame = requestAnimationFrame(tick);
});

onBeforeUnmount(() => cancelAnimationFrame(frame));

/* ---------- staggered entrance of cards and plan tiles ---------- */
const stagger = (step, max) => (el) => {
    el.style.transitionDelay = `${Math.min(Number(el.dataset.index ?? 0), max) * step}ms`;
};
const clearDelay = (el) => {
    el.style.transitionDelay = "";
};

const cardBeforeEnter = stagger(80, 8);
const planBeforeEnter = stagger(55, 8);
</script>

<template>
    <component :is="layoutComponent">
        <Head title="Network Offers" />

        <div class="space-y-8">
            <!-- Hero -->
            <header
                class="hero relative overflow-hidden rounded-3xl border border-surface-3 bg-gradient-to-br from-primary/15 via-surface-2 to-surface-2 px-6 py-10 sm:px-10 sm:py-12"
            >
                <div class="blob blob-1 bg-primary/30" aria-hidden="true"></div>
                <div
                    class="blob blob-2 bg-green-500/20"
                    aria-hidden="true"
                ></div>

                <div class="relative">
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            class="flex flex-wrap items-start justify-between gap-5"
                        >
                            <div class="max-w-2xl">
                                <p
                                    class="rise inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary"
                                    style="--d: 0ms"
                                >
                                    <span class="pulse-dot"></span>
                                    Available plans
                                </p>
                                <h1
                                    class="rise mt-4 bg-gradient-to-r from-ink-900 via-ink-900 to-primary bg-clip-text text-4xl font-extrabold tracking-tight text-transparent sm:text-5xl"
                                    style="--d: 90ms"
                                >
                                    Network Offers
                                </h1>
                                <p
                                    class="rise mt-3 text-base text-ink-500 sm:text-lg"
                                    style="--d: 180ms"
                                >
                                    Browse provider plan prices and included
                                    benefits. Each price shows the same benefits for
                                    PIN and direct top-up.
                                </p>
                            </div>
                        </div>
    
                        <div>
                            <!-- Numbers -->
                            <div class="mt-8 grid max-w-xl grid-cols-3 gap-3">
                                <div
                                    v-for="(item, i) in [
                                        { label: 'Networks', value: shown.networks },
                                        { label: 'Plans', value: shown.plans },
                                        { label: 'Countries', value: shown.countries },
                                    ]"
                                    :key="item.label"
                                    class="rise rounded-2xl border border-surface-3 bg-surface-0/70 px-4 py-3 backdrop-blur"
                                    :style="{ '--d': 340 + i * 90 + 'ms' }"
                                >
                                    <div
                                        class="text-2xl font-bold tabular-nums text-ink-900 sm:text-3xl"
                                    >
                                        {{ item.value }}
                                    </div>
                                    <div class="text-xs text-ink-500">
                                        {{ item.label }}
                                    </div>
                                </div>
                            </div>
        
                            <!-- Search + sort -->
                            <div
                                class="rise mt-8 flex flex-wrap items-center gap-3"
                                style="--d: 520ms"
                            >
                                <div class="relative w-full sm:max-w-sm">
                                    <svg
                                        class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"
                                        />
                                    </svg>
                                    <input
                                        v-model="search"
                                        type="text"
                                        placeholder="Search networks or countries..."
                                        aria-label="Search network offers"
                                        class="search w-full rounded-xl border border-surface-3 bg-surface-0 py-3 pl-10 pr-10 text-sm text-ink-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15"
                                    />
                                    <button
                                        v-if="search"
                                        type="button"
                                        @click="search = ''"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 transition hover:text-ink-900"
                                        aria-label="Clear search"
                                    >
                                        &times;
                                    </button>
                                </div>
        
                                <select
                                    v-model="sortBy"
                                    aria-label="Sort networks"
                                    class="rounded-xl border border-surface-3 bg-surface-0 px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-primary"
                                >
                                    <option value="name">Sort: A – Z</option>
                                    <option value="price">
                                        Sort: Lowest price first
                                    </option>
                                    <option value="plans">
                                        Sort: Most plans first
                                    </option>
                                </select>
                            </div>
        
                            <!-- Country chips -->
                            <div
                                v-if="countries.length > 1"
                                class="rise chips mt-4 flex gap-2 overflow-x-auto pb-1"
                                style="--d: 600ms"
                            >
                                <button
                                    type="button"
                                    @click="country = ''"
                                    class="chip"
                                    :class="
                                        country === ''
                                            ? 'chip-active bg-primary text-ink-100'
                                            : 'bg-surface-3 text-ink-700 hover:border-primary/50'
                                    "
                                >
                                    All countries
                                </button>
                                <button
                                    v-for="c in countries"
                                    :key="c.key"
                                    type="button"
                                    @click="country = country === c.key ? '' : c.key"
                                    class="chip"
                                    :class="
                                        country === c.key
                                            ? 'chip-active bg-primary text-ink-100'
                                            : 'bg-surface-3 text-ink-700 hover:border-primary/50'
                                    "
                                >
                                    <span v-if="flag(c.iso)">{{ flag(c.iso) }}</span>
                                    {{ c.name }}
                                    <span class="chip-count">{{ c.count }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Result line -->
            <div
                v-if="networks.length"
                class="flex items-center justify-between text-sm text-ink-500 px-5"
            >
                <span>
                    Showing
                    <strong class="text-ink-900">{{
                        visibleNetworks.length
                    }}</strong>
                    of {{ networks.length }} networks
                </span>
                <button
                    v-if="hasFilters"
                    type="button"
                    @click="clearFilters"
                    class="font-medium text-primary transition hover:text-primary-dark"
                >
                    Clear filters
                </button>
            </div>

            <!-- Networks -->
            <TransitionGroup
                v-if="visibleNetworks.length"
                tag="div"
                name="cards"
                appear
                class="grid grid-cols-1 gap-6 lg:grid-cols-2 p-5"
                @before-enter="cardBeforeEnter"
                @after-enter="clearDelay"
            >
                <article
                    v-for="(network, index) in visibleNetworks"
                    :key="network.id"
                    :data-index="index"
                    class="network-card overflow-hidden rounded-2xl border border-surface-3 bg-surface-0 hover:border-primary/40"
                >
                    <!-- Network header -->
                    <div
                        class="network-head relative flex items-center gap-4 border-b border-surface-3 bg-surface-2 p-5 text-primary"
                    >
                        <div
                            class="logo flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-surface-3"
                        >
                            <img
                                v-if="
                                    network.logo_url && !failedLogos[network.id]
                                "
                                :src="network.logo_url"
                                :alt="`${network.name} logo`"
                                loading="lazy"
                                class="h-full w-full object-contain p-2"
                                @error="failedLogos[network.id] = true"
                            />
                            <span v-else class="text-lg font-bold text-primary">
                                {{ initials(network.name) }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2
                                class="truncate text-xl font-semibold text-ink-900"
                            >
                                {{ network.name }}
                            </h2>
                            <p
                                class="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-500"
                            >
                                <span v-if="flag(network.country_iso)">{{
                                    flag(network.country_iso)
                                }}</span>
                                <span>{{ network.country_name }}</span>
                                <span v-if="network.country_iso"
                                    >· {{ network.country_iso }}</span
                                >
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <div
                                v-if="lowestPrice(network) !== null"
                                class="rounded-xl bg-primary/10 px-3 py-1.5"
                            >
                                <div
                                    class="text-[10px] uppercase tracking-wide text-ink-500"
                                >
                                    From
                                </div>
                                <div class="text-sm font-bold text-primary">
                                    {{ plansOf(network)[0].currency }}
                                    {{ price(lowestPrice(network)) }}
                                </div>
                            </div>
                            <div class="mt-1 text-xs text-ink-500">
                                {{ plansOf(network).length }}
                                {{
                                    plansOf(network).length === 1
                                        ? "plan"
                                        : "plans"
                                }}
                            </div>
                        </div>
                    </div>

                    <!-- Plans -->
                    <div class="p-4 sm:p-5">
                        <TransitionGroup
                            v-if="plansOf(network).length"
                            tag="div"
                            name="plan"
                            appear
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                            @before-enter="planBeforeEnter"
                            @after-enter="clearDelay"
                        >
                            <section
                                v-for="(plan, i) in visiblePlans(network)"
                                :key="plan.id"
                                :data-index="i"
                                class="plan relative rounded-xl border border-surface-3 bg-surface-2/60 p-4 hover:border-primary/50"
                            >
                                <span
                                    v-if="
                                        i === 0 && plansOf(network).length > 1
                                    "
                                    class="absolute right-3 top-3 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-green-600"
                                >
                                    Lowest price
                                </span>

                                <div class="flex items-baseline gap-1.5">
                                    <span
                                        class="text-xs font-semibold text-ink-500"
                                        >{{ plan.currency }}</span
                                    >
                                    <span
                                        class="text-3xl font-extrabold tracking-tight text-ink-900"
                                    >
                                        {{ price(plan.price) }}
                                    </span>
                                </div>

                                <ul class="mt-3 space-y-1.5">
                                    <li
                                        v-for="benefit in plan.benefits"
                                        :key="benefit"
                                        class="flex items-start gap-2 text-sm text-ink-700"
                                    >
                                        <svg
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span>{{ benefit }}</span>
                                    </li>
                                </ul>
                            </section>
                        </TransitionGroup>

                        <p v-else class="py-4 text-center text-sm text-ink-500">
                            No plans published for this network yet.
                        </p>

                        <button
                            v-if="hiddenCount(network) > 0"
                            type="button"
                            @click="
                                expanded[network.id] = !expanded[network.id]
                            "
                            class="more mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-surface-3 py-2.5 text-sm font-medium text-ink-700 transition hover:border-primary hover:text-primary"
                        >
                            {{
                                expanded[network.id]
                                    ? "Show fewer plans"
                                    : `Show ${hiddenCount(network)} more ${hiddenCount(network) === 1 ? "plan" : "plans"}`
                            }}
                            <svg
                                class="h-4 w-4 transition-transform duration-300"
                                :class="
                                    expanded[network.id] ? 'rotate-180' : ''
                                "
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
                    </div>
                </article>
            </TransitionGroup>

            <!-- Empty state -->
            <div
                v-else
                class="empty rounded-3xl border border-dashed border-surface-3 bg-surface-2 px-6 py-16 text-center"
            >
                <div
                    class="empty-icon mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <svg
                        class="h-8 w-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"
                        />
                    </svg>
                </div>
                <h2 class="mt-5 text-lg font-semibold text-ink-900">
                    {{
                        hasFilters
                            ? "No matching network offers"
                            : "No network offers available"
                    }}
                </h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-ink-500">
                    {{
                        hasFilters
                            ? "Try a different search or country."
                            : "Provider plan details will appear here once they are published."
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    @click="clearFilters"
                    class="mt-5 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-ink-100 transition hover:bg-primary-dark"
                >
                    Clear filters
                </button>
            </div>
        </div>
    </component>
</template>

<style scoped>
/* ---------- hero ---------- */
.blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(60px);
    opacity: 0.55;
    pointer-events: none;
    animation: float 14s ease-in-out infinite;
}
.blob-1 {
    top: -80px;
    right: -60px;
    width: 280px;
    height: 280px;
}
.blob-2 {
    bottom: -100px;
    left: -40px;
    width: 240px;
    height: 240px;
    animation-duration: 18s;
    animation-direction: reverse;
}

@keyframes float {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(28px, -22px, 0) scale(1.12);
    }
}

.rise {
    opacity: 0;
    transform: translateY(14px);
    animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--d, 0ms);
}

@keyframes rise {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pulse-dot {
    position: relative;
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: currentColor;
}
.pulse-dot::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: currentColor;
    animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}

.cta {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.5);
}
.cta-arrow {
    display: inline-block;
    transition: transform 0.2s ease;
}
.cta:hover .cta-arrow {
    transform: translateX(4px);
}

/* ---------- chips ---------- */
.chips {
    scrollbar-width: none;
}
.chips::-webkit-scrollbar {
    display: none;
}

.chip {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid transparent;
    border-radius: 9999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 500;
    white-space: nowrap;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
}
.chip:hover {
    transform: translateY(-1px);
}
.chip:active {
    transform: scale(0.96);
}
.chip-count {
    border-radius: 9999px;
    padding: 0 0.4rem;
    font-size: 0.7rem;
    background: rgba(127, 127, 127, 0.2);
}
.chip-active .chip-count {
    background: rgba(255, 255, 255, 0.25);
}

/* ---------- cards ---------- */
.network-card {
    transition:
        transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.35s ease,
        border-color 0.35s ease;
}
.network-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px -20px rgba(0, 0, 0, 0.35);
}

.network-head::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: -1px;
    height: 2px;
    width: 0;
    background: currentColor;
    transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.network-card:hover .network-head::after {
    width: 100%;
}

.logo {
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.network-card:hover .logo {
    transform: scale(1.08) rotate(-3deg);
}

.plan {
    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        background-color 0.25s ease,
        box-shadow 0.25s ease;
}
.plan:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -16px rgba(0, 0, 0, 0.45);
}

/* list transitions (filtering, sorting, show more) */
.cards-enter-active,
.cards-move {
    transition:
        opacity 0.5s ease,
        transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}
.cards-enter-from {
    opacity: 0;
    transform: translateY(24px) scale(0.98);
}

.plan-enter-active,
.plan-move {
    transition:
        opacity 0.4s ease,
        transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}
.plan-enter-from {
    opacity: 0;
    transform: translateY(12px) scale(0.97);
}

/* ---------- empty state ---------- */
.empty {
    animation: rise 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.empty-icon {
    animation: bob 3s ease-in-out infinite;
}
@keyframes bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

/* ---------- respect reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .blob,
    .pulse-dot::after,
    .empty-icon {
        animation: none;
    }
    .rise,
    .empty {
        animation: none;
        opacity: 1;
        transform: none;
    }
    .cards-enter-active,
    .cards-move,
    .plan-enter-active,
    .plan-move,
    .network-card,
    .plan,
    .logo,
    .chip,
    .cta {
        transition: none;
    }
    .network-card:hover,
    .plan:hover,
    .network-card:hover .logo,
    .cta:hover,
    .chip:hover {
        transform: none;
    }
}
</style>
