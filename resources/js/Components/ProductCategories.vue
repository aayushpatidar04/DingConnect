<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
    products: { type: Array, default: () => [] },
    loading: Boolean,
    logo: String,
});

const emit = defineEmits(["select-product"]);

// Category icons and colors
const categoryConfig = {
    TopUp: {
        icon: "📱",
        label: "TopUp",
        bgClass: "bg-blue-100 border-blue-400",
        activeClass: "bg-blue-500/25 border-blue-400 ring-2 ring-blue-500/40",
        textColor: "text-blue-800",
    },
    Data: {
        icon: "📶",
        label: "Data",
        bgClass: "bg-green-500/10 border-green-500/30",
        activeClass:
            "bg-green-500/25 border-green-400 ring-2 ring-green-500/40",
        textColor: "text-green-400",
    },
    Bundle: {
        icon: "📦",
        label: "Bundle",
        bgClass: "bg-purple-500/10 border-purple-500/30",
        activeClass:
            "bg-purple-500/25 border-purple-400 ring-2 ring-purple-500/40",
        textColor: "text-purple-400",
    },
    PIN: {
        icon: "🔢",
        label: "PIN",
        bgClass: "bg-orange-500/10 border-orange-500/30",
        activeClass:
            "bg-orange-500/25 border-orange-400 ring-2 ring-orange-500/40",
        textColor: "text-orange-400",
    },
    LDI: {
        icon: "📞",
        label: "LDI",
        bgClass: "bg-cyan-500/10 border-cyan-500/30",
        activeClass: "bg-cyan-500/25 border-cyan-400 ring-2 ring-cyan-500/40",
        textColor: "text-cyan-400",
    },
    Voucher: {
        icon: "🎫",
        label: "Voucher",
        bgClass: "bg-pink-500/10 border-pink-500/30",
        activeClass: "bg-pink-500/25 border-pink-400 ring-2 ring-pink-500/40",
        textColor: "text-pink-400",
    },
    DTH: {
        icon: "📺",
        label: "DTH",
        bgClass: "bg-yellow-500/10 border-yellow-500/30",
        activeClass:
            "bg-yellow-500/25 border-yellow-400 ring-2 ring-yellow-500/40",
        textColor: "text-yellow-600",
    },
};

const categoryOrder = [
    "TopUp",
    "Data",
    "Bundle",
    "PIN",
    "LDI",
    "Voucher",
    "DTH",
];

function determineTransferType(product) {
    const { benefits = [], redemption_type } = product;
    const b = (benefits || []).map((v) => String(v).toLowerCase());

    if (redemption_type === "Immediate") {
        if (
            b.includes("mobile") &&
            b.includes("minutes") &&
            !b.includes("data")
        )
            return "TopUp";
        if (
            b.includes("mobile") &&
            b.includes("data") &&
            !b.includes("minutes")
        )
            return "Data";
        if (b.includes("mobile") && b.includes("minutes") && b.includes("data"))
            return "Bundle";
        if (b.includes("tv") || b.includes("utility")) return "DTH";
    }

    if (redemption_type === "ReadReceipt") {
        if (b.includes("mobile") && b.includes("minutes") && b.includes("data"))
            return "PIN";
        if (b.includes("longdistance") || b.includes("minutes")) return "LDI";
        if (b.includes("digital product")) return "Voucher";
    }

    return "TopUp"; // fallback
}

// { TopUp: [...], Data: [...], ... }
const grouped = computed(() => {
    const map = {};
    for (const product of props.products || []) {
        const key = determineTransferType(product);
        (map[key] ||= []).push(product);
    }
    return map;
});

// Only categories that actually have products, in your preferred order
const activeCategories = computed(() =>
    categoryOrder.filter((cat) => grouped.value[cat]?.length),
);

// Active tab
const activeTab = ref(null);

watch(
    activeCategories,
    (list) => {
        if (!list.length) {
            activeTab.value = null;
        } else if (!list.includes(activeTab.value)) {
            // activeTab.value = list[0];
        }
    },
    { immediate: true },
);

const visibleProducts = computed(() =>
    activeTab.value ? grouped.value[activeTab.value] || [] : [],
);

function selectProduct(product) {
    emit("select-product", product);
}

function formatValidity(iso) {
    if (!iso || iso.trim() === "") return null;
    const match = iso.match(/P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?/);
    if (!match) return null;

    const parts = [];
    if (match[1])
        parts.push(match[1] + " year" + (parseInt(match[1]) > 1 ? "s" : ""));
    if (match[2])
        parts.push(match[2] + " month" + (parseInt(match[2]) > 1 ? "s" : ""));
    if (match[3])
        parts.push(match[3] + " week" + (parseInt(match[3]) > 1 ? "s" : ""));
    if (match[4])
        parts.push(match[4] + " day" + (parseInt(match[4]) > 1 ? "s" : ""));

    if (parts.length === 0) return null;
    return "Valid for " + parts.join(", ");
}
</script>

<template>
    <div v-if="products.length > 0">
        <!-- Category Tabs -->
        <div class="flex gap-2 mb-6 justify-center overflow-x-auto pb-1" role="tablist">
            <button
                v-for="category in activeCategories"
                :key="category"
                type="button"
                role="tab"
                :aria-selected="activeTab === category"
                @click="activeTab = category"
                :class="[
                    'px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 border whitespace-nowrap',
                    categoryConfig[category].textColor,
                    activeTab === category
                        ? categoryConfig[category].activeClass
                        : categoryConfig[category].bgClass +
                          ' opacity-60 hover:opacity-100',
                ]"
            >
                <!-- <span>{{ categoryConfig[category].icon }}</span> -->
                {{ category === 'TopUp' ? 'Direct Top Up' : category }}
                <span class="bg-surface-3/70 px-2 py-0.5 rounded-full text-xs">
                    {{ grouped[category].length }}
                </span>
            </button>
        </div>

        <!-- Only the active tab's products are rendered -->
        <div
            :key="activeTab"
            role="tabpanel"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
        >
            <button
                v-for="product in visibleProducts"
                :key="product.sku_code"
                type="button"
                @click="selectProduct(product)"
                :class="[
                    'bg-surface-2 border rounded-2xl p-5 text-left transition group relative',
                    product.is_denomination
                        ? 'border-surface-3 hover:border-primary'
                        : 'border-primary/30 hover:border-primary bg-primary/5',
                ]"
            >
                <!-- Display Text -->
                <div class="flex justify-between">
                    <div class="font-semibold text-ink-900 mb-2 pr-16">
                        {{ product.display_text || product.sku_code }}
                    </div>
                    <div v-if="props.logo" class="absolute top-4 right-4 w-12 h-12">
                        <img
                            :src="props.logo"
                            alt="Provider Logo"
                            class="w-full h-full object-contain"
                        />
                    </div>
                </div>

                <!-- Benefits Tags -->
                <div
                    v-if="product.benefits && product.benefits.length"
                    class="flex flex-wrap gap-1 mb-3"
                >
                    <span
                        v-for="benefit in product.benefits"
                        :key="benefit"
                        class="text-xs bg-surface-3 text-ink-500 px-2 py-0.5 rounded-full"
                    >
                        {{ benefit }}
                    </span>
                </div>

                <!-- Pricing -->
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-xl font-bold text-ink-900">
                        £{{ Number(product.send_value || 0).toFixed(2) }}
                    </span>
                </div>

                <!-- Validity Period -->
                <div
                    v-if="product.validity_period"
                    class="text-xs text-ink-500"
                >
                    {{ formatValidity(product.validity_period) }}
                </div>

                <!-- Redemption Type Badge -->
                <div
                    v-if="product.redemption_type === 'ReadReceipt'"
                    class="mt-2"
                >
                    <span
                        class="text-xs bg-yellow-500/10 text-yellow-600 px-2 py-1 rounded-full"
                    >
                        📋 PIN/Voucher required
                    </span>
                </div>
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="text-center py-12">
        <div
            class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"
        ></div>
        <p class="mt-4 text-ink-500">Loading products...</p>
    </div>
</template>
