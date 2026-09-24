<script setup>
import { computed } from "vue";

const props = defineProps({
    products: Array,
    loading: Boolean,
});

// Category icons and colors
const categoryConfig = {
    TopUp: {
        icon: "📱",
        label: "TopUp",
        color: "blue",
        bgClass: "bg-blue-500/10 border-blue-500/30",
        iconBg: "bg-blue-500/20",
        textColor: "text-blue-400",
        checkBenefits: (b, r, t) =>
            t === "TopUp" &&
            r === "Immediate" &&
            b.includes("Mobile") &&
            b.includes("Minutes") &&
            !b.includes("Data"),
    },
    Data: {
        icon: "📶",
        label: "Data",
        color: "green",
        bgClass: "bg-green-500/10 border-green-500/30",
        iconBg: "bg-green-500/20",
        textColor: "text-green-400",
        checkBenefits: (b, r, t) =>
            t === "Data" &&
            r === "Immediate" &&
            b.includes("Mobile") &&
            b.includes("Data") &&
            !b.includes("Minutes"),
    },
    Bundle: {
        icon: "📦",
        label: "Bundle",
        color: "purple",
        bgClass: "bg-purple-500/10 border-purple-500/30",
        iconBg: "bg-purple-500/20",
        textColor: "text-purple-400",
        checkBenefits: (b, r, t) =>
            t === "Bundle" &&
            r === "Immediate" &&
            b.includes("Mobile") &&
            b.includes("Minutes") &&
            b.includes("Data"),
    },
    PIN: {
        icon: "🔢",
        label: "PIN",
        color: "orange",
        bgClass: "bg-orange-500/10 border-orange-500/30",
        iconBg: "bg-orange-500/20",
        textColor: "text-orange-400",
        checkBenefits: (b, r, t) =>
            t === "PIN" &&
            r === "ReadReceipt" &&
            b.includes("Mobile") &&
            b.includes("Minutes") &&
            b.includes("Data"),
    },
    LDI: {
        icon: "📞",
        label: "LDI",
        color: "cyan",
        bgClass: "bg-cyan-500/10 border-cyan-500/30",
        iconBg: "bg-cyan-500/20",
        textColor: "text-cyan-400",
        checkBenefits: (b, r, t) =>
            t === "LDI" &&
            r === "ReadReceipt" &&
            (b.includes("LongDistance") || b.includes("Minutes")),
    },
    Voucher: {
        icon: "🎫",
        label: "Voucher",
        color: "pink",
        bgClass: "bg-pink-500/10 border-pink-500/30",
        iconBg: "bg-pink-500/20",
        textColor: "text-pink-400",
        checkBenefits: (b, r, t) =>
            t === "Voucher" &&
            r === "ReadReceipt" &&
            b.includes("Digital Product"),
    },
    DTH: {
        icon: "📺",
        label: "DTH",
        color: "yellow",
        bgClass: "bg-yellow-500/10 border-yellow-500/30",
        iconBg: "bg-yellow-500/20",
        textColor: "text-yellow-400",
        checkBenefits: (b, r, t) =>
            t === "DTH" &&
            r === "Immediate" &&
            (b.includes("TV") || b.includes("Utility")),
    },
};

function determineTransferType(product) {
    const { benefits = [], redemption_type } = product;

    // Normalize benefits for consistent matching
    const b = benefits.map((v) => v.toLowerCase());

    if (redemption_type === "Immediate") {
        if (
            b.includes("mobile") &&
            b.includes("minutes") &&
            !b.includes("data")
        ) {
            return "TopUp";
        }
        if (
            b.includes("mobile") &&
            b.includes("data") &&
            !b.includes("minutes")
        ) {
            return "Data";
        }
        if (
            b.includes("mobile") &&
            b.includes("minutes") &&
            b.includes("data")
        ) {
            return "Bundle";
        }
        if (b.includes("tv") || b.includes("utility")) {
            return "DTH";
        }
    }

    if (redemption_type === "ReadReceipt") {
        if (
            b.includes("mobile") &&
            b.includes("minutes") &&
            b.includes("data")
        ) {
            return "PIN";
        }
        if (b.includes("longdistance") || b.includes("minutes")) {
            return "LDI";
        }
        if (b.includes("digital product")) {
            return "Voucher";
        }
    }

    return "TopUp"; // fallback
}

function categorizeProduct(product) {
    const transferType = determineTransferType(product);
    const category = categoryConfig[transferType];
    return category || categoryConfig.TopUp;
}

const categorizedProducts = computed(() => {
    if (!props.products || props.products.length === 0) return {};

    const categories = {};
    props.products.forEach((product) => {
        const category = categorizeProduct(product);
        if (!categories[category.label]) {
            categories[category.label] = {
                ...category,
                products: [],
            };
        }
        categories[category.label].products.push(product);
    });

    return categories;
});

const categoryOrder = [
    "TopUp",
    "Data",
    "Bundle",
    "PIN",
    "LDI",
    "Voucher",
    "DTH",
];
const activeCategories = computed(() => {
    return categoryOrder.filter((cat) => categorizedProducts.value[cat]);
});

const emit = defineEmits(["select-product"]);

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
        <div class="flex flex-wrap gap-2 mb-6">
            <button
                v-for="category in activeCategories"
                :key="category"
                @click=""
                :class="[
                    'px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 border',
                    categoryConfig[category]?.bgClass ||
                        'bg-dark-700 border-dark-600',
                    categoryConfig[category]?.textColor || 'text-white',
                ]"
            >
                <span>{{ categoryConfig[category]?.icon }}</span>
                {{ category }}
                <span class="bg-dark-700/50 px-2 py-0.5 rounded-full text-xs">
                    {{ categorizedProducts[category].products.length }}
                </span>
            </button>
        </div>

        <!-- Products Grid by Category -->
        <div v-for="category in activeCategories" :key="category" class="mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div
                    :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center',
                        categoryConfig[category]?.iconBg,
                    ]"
                >
                    <span class="text-lg">{{
                        categoryConfig[category]?.icon
                    }}</span>
                </div>
                <h3
                    :class="[
                        'text-lg font-semibold',
                        categoryConfig[category]?.textColor,
                    ]"
                >
                    {{ category }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <button
                    v-for="product in categorizedProducts[category].products"
                    :key="product.sku_code"
                    @click="selectProduct(product)"
                    :class="[
                        'bg-dark-800 border rounded-2xl p-5 text-left transition group relative',
                        product.is_denomination
                            ? 'border-dark-600 hover:border-primary'
                            : 'border-primary/30 hover:border-primary bg-primary/5',
                    ]"
                >
                    <!-- Free Range Badge -->
                    <div
                        v-if="!product.is_denomination"
                        class="absolute top-3 right-3"
                    >
                        <span
                            class="bg-primary/20 text-primary-light text-xs px-2 py-1 rounded-full font-medium"
                        >
                            Free Range
                        </span>
                    </div>

                    <!-- Display Text -->
                    <div class="font-semibold text-white mb-2 pr-16">
                        {{ product.display_text || product.sku_code }}
                    </div>

                    <!-- Benefits Tags -->
                    <div
                        v-if="product.benefits && product.benefits.length"
                        class="flex flex-wrap gap-1 mb-3"
                    >
                        <span
                            v-for="benefit in product.benefits"
                            :key="benefit"
                            class="text-xs bg-dark-700 text-dark-300 px-2 py-0.5 rounded-full"
                        >
                            {{ benefit }}
                        </span>
                    </div>

                    <!-- Pricing -->
                    <div class="flex items-baseline gap-2 mb-2">
                        <span class="text-xl font-bold text-white">
                            £{{ product.send_value.toFixed(2) }}
                        </span>
                        <!-- <span v-if="product.receive_value && product.receive_value !== product.send_value" class="text-sm text-green-400">
                            → £{{ product.receive_value.toFixed(2) }}
                        </span> -->
                    </div>

                    <!-- Validity Period -->
                    <div
                        v-if="product.validity_period"
                        class="text-xs text-dark-400"
                    >
                        {{ formatValidity(product.validity_period) }}
                    </div>

                    <!-- Redemption Type Badge -->
                    <div
                        v-if="product.redemption_type === 'ReadReceipt'"
                        class="mt-2"
                    >
                        <span
                            class="text-xs bg-yellow-500/10 text-yellow-300 px-2 py-1 rounded-full"
                        >
                            📋 PIN/Voucher required
                        </span>
                    </div>

                    <!-- Product Type -->
                    <div
                        v-if="product.product_type"
                        class="text-xs text-dark-500 mt-1"
                    >
                        {{ product.product_type }}
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="text-center py-12">
        <div
            class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"
        ></div>
        <p class="mt-4 text-dark-300">Loading products...</p>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 text-dark-400">
        No products available. Please select an operator first.
    </div>
</template>
