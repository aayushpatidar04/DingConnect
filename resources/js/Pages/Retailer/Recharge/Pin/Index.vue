<script setup>
import { Head } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import { ref, computed, onMounted } from "vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    countries: Array,
    providers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    selectedCountry: String,
    selectedProvider: String,
});

const step = ref(props.selectedCountry ? (props.selectedProvider ? 3 : 2) : 1);
const country = ref(props.selectedCountry || "");
const provider = ref(props.selectedProvider || "");
const selectedProduct = ref(null);
const serialNumber = ref("");
const loading = ref(false);
const errorMsg = ref("");
const localProviders = ref([]);
const localProducts = ref([]);

// Use local data if fetched, fallback to props
const providerList = computed(() => localProviders.value.length ? localProviders.value : (props.providers || []));

const selectedProviders = computed(() => {
    if (!country.value) return [];
    return providerList.value.filter((p) => p.country_iso === country.value);
});

const pinProducts = computed(() => {
    const source = localProducts.value.length ? localProducts.value : (props.products || []);
    return source.filter((p) => p.redemption_type === "ReadReceipt");
});

async function loadProviders() {
    if (!country.value || loading.value) return;
    loading.value = true;
    errorMsg.value = "";
    try {
        const res = await fetch(
            `/retailer/recharge/providers?country_iso=${country.value}`,
        );
        const data = await res.json();
        if (data.success && data.providers?.length) {
            localProviders.value = data.providers;
            step.value = 2;
        } else {
            errorMsg.value = "No providers available for this country.";
        }
    } catch {
        errorMsg.value = "Failed to load providers.";
    } finally {
        loading.value = false;
    }
}

function selectProvider() {
    if (!provider.value) return;
    loading.value = true;
    errorMsg.value = "";
    fetch(
        `/retailer/recharge/products?country_iso=${country.value}&provider_code=${provider.value}`,
    )
        .then((r) => r.json())
        .then((data) => {
            if (data.success && data.products?.length) {
                localProducts.value = data.products;
                step.value = 3;
            } else {
                errorMsg.value =
                    "No ReadReceipt products available for this provider.";
            }
        })
        .catch(() => {
            errorMsg.value = "Failed to load products.";
        })
        .finally(() => {
            loading.value = false;
        });
}

function selectProduct(product) {
    selectedProduct.value = product;
    step.value = 4;
}

async function checkAndConfirm() {
    if (!serialNumber.value.trim()) return;
    loading.value = true;
    errorMsg.value = "";
    try {
        const res = await fetch(
            `/retailer/recharge/pin/check-serial?number=${encodeURIComponent(serialNumber.value.trim())}`,
        );
        const data = await res.json();
        if (data.allowed) {
            step.value = 5;
        } else {
            errorMsg.value =
                "This number/serial is not authorized for recharge.";
        }
    } catch {
        errorMsg.value = "Validation failed.";
    } finally {
        loading.value = false;
    }
}

async function confirmRecharge() {
    if (!selectedProduct.value || !serialNumber.value.trim()) return;
    loading.value = true;
    errorMsg.value = "";
    try {
        const res = await fetch("/retailer/recharge/pin/process", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content || "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                sku_code: selectedProduct.value.sku_code,
                send_value:
                    selectedProduct.value.send_value ||
                    selectedProduct.value.max_send_value,
                serial_number: serialNumber.value.trim(),
                redemption_type: "ReadReceipt",
                provider_code: provider.value,
            }),
        });
        const data = await res.json();
        if (data.success || data.transaction_id) {
            window.location.href = "/retailer/transactions";
        } else {
            errorMsg.value = data.error || "Recharge failed. Please try again.";
        }
    } catch {
        errorMsg.value = "Something went wrong.";
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="PIN Recharge - MK Network" />
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-1">PIN Recharge</h1>
            <p class="text-dark-300">
                Purchase a ReadReceipt voucher. No mobile number required — just
                enter a serial for your records.
            </p>
        </div>

        <!-- Steps -->
        <div class="flex items-center gap-2 mb-8">
            <template
                v-for="(s, i) in [
                    'Country',
                    'Provider',
                    'Product',
                    'Serial',
                    'Confirm',
                ]"
                :key="i"
            >
                <div
                    class="flex items-center gap-2"
                    :class="i < 4 ? 'flex-1' : ''"
                >
                    <div
                        :class="[
                            'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                            step > i + 1
                                ? 'bg-green-500 text-white'
                                : step === i + 1
                                  ? 'bg-primary text-white'
                                  : 'bg-dark-700 text-dark-400',
                        ]"
                    >
                        {{ step > i + 1 ? "✓" : i + 1 }}
                    </div>
                    <span
                        :class="[
                            'text-xs hidden sm:inline',
                            step >= i + 1 ? 'text-white' : 'text-dark-400',
                        ]"
                    >
                        {{ s }}
                    </span>
                    <span
                        v-if="i < 4"
                        class="flex-1 h-px bg-dark-700 mx-2"
                    ></span>
                </div>
            </template>
        </div>

        <!-- Error -->
        <div
            v-if="errorMsg"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 mb-6"
        >
            <p class="text-sm text-red-300">{{ errorMsg }}</p>
        </div>

        <!-- Step 1: Country -->
        <div
            v-if="step === 1"
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6"
        >
            <h2 class="text-lg font-bold text-white mb-1">Select Country</h2>
            <p class="text-sm text-dark-300 mb-5">
                Choose the country for your PIN voucher.
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <button
                    v-for="c in countries"
                    :key="c.iso_code"
                    @click="country = c.iso_code"
                    :class="[
                        'flex items-center gap-3 p-3 rounded-xl border transition',
                        country === c.iso_code
                            ? 'border-primary bg-primary/10'
                            : 'border-dark-600 bg-dark-700 hover:border-dark-500',
                    ]"
                >
                    <span class="text-xl text-primary">{{ c.flag_emoji }}</span>
                    <span class="text-sm font-medium text-white">{{
                        c.name
                    }}</span>
                </button>
            </div>
            <div class="mt-6 flex justify-end">
                <button
                    @click="loadProviders"
                    :disabled="!country || loading"
                    class="px-6 py-2.5 bg-primary text-white rounded-xl font-medium hover:bg-primary-dark transition disabled:opacity-50"
                >
                    {{ loading ? "Loading..." : "Continue →" }}
                </button>
            </div>
        </div>

        <!-- Step 2: Provider -->
        <div
            v-if="step === 2"
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6"
        >
            <h2 class="text-lg font-bold text-white mb-1">Select Provider</h2>
            <p class="text-sm text-dark-300 mb-5">
                Choose the network operator.
            </p>

            <div v-if="loading" class="text-center text-dark-400 py-8">
                Loading providers...
            </div>

            <div
                v-else-if="selectedProviders.length === 0"
                class="text-center text-dark-400 py-8"
            >
                No providers found. Go back and try another country.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    v-for="p in selectedProviders"
                    :key="p.provider_code"
                    @click="provider = p.provider_code"
                    :class="[
                        'flex items-center gap-3 p-4 rounded-xl border text-left transition',
                        provider === p.provider_code
                            ? 'border-primary bg-primary/10'
                            : 'border-dark-600 bg-dark-700 hover:border-dark-500',
                    ]"
                >
                    <div
                        class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden shrink-0"
                    >
                        <img
                            v-if="p.logo_url"
                            :src="p.logo_url"
                            class="w-full h-full object-contain p-1"
                            @error="$event.target.style.display = 'none'"
                        />
                        <span v-else>📱</span>
                    </div>
                    <div>
                        <div class="font-medium text-white">{{ p.name }}</div>
                        <div class="text-xs text-dark-400">
                            {{ p.provider_code }}
                        </div>
                    </div>
                </button>
            </div>

            <div class="mt-6 flex justify-between">
                <button
                    @click="step = 1"
                    class="px-6 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                >
                    ← Back
                </button>
                <button
                    @click="selectProvider"
                    :disabled="!provider || loading"
                    class="px-6 py-2.5 bg-primary text-white rounded-xl font-medium hover:bg-primary-dark transition disabled:opacity-50"
                >
                    Continue →
                </button>
            </div>
        </div>

        <!-- Step 3: Product -->
        <div
            v-if="step === 3"
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6"
        >
            <h2 class="text-lg font-bold text-white mb-1">
                Select PIN Product
            </h2>
            <p class="text-sm text-dark-300 mb-5">
                Choose a PIN voucher. A code will be generated after purchase.
            </p>

            <div v-if="loading" class="text-center text-dark-400 py-8">
                Loading products...
            </div>

            <div
                v-else-if="pinProducts.length === 0"
                class="text-center text-dark-400 py-8"
            >
                No PIN products available for this provider.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button
                    v-for="product in pinProducts"
                    :key="product.sku_code"
                    @click="selectProduct(product)"
                    class="p-5 rounded-xl border border-dark-600 bg-dark-700 hover:border-primary text-left transition"
                >
                    <div class="flex items-start justify-between mb-2">
                        <div class="font-semibold text-white">
                            {{ product.display_text || product.sku_code }}
                        </div>
                        <span
                            class="text-xs bg-yellow-500/20 text-yellow-300 px-2 py-0.5 rounded-full"
                            >PIN</span
                        >
                    </div>
                    <div class="text-xs text-dark-400 mb-3">
                        SKU: {{ product.sku_code }}
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs text-dark-400">You Pay</div>
                            <div class="text-sm font-bold text-white">
                                {{ product.send_currency || "GBP" }}
                                {{ (product.send_value || 0).toFixed(2) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-dark-400">PIN Value</div>
                            <div class="text-sm font-bold text-yellow-400">
                                {{ product.receive_currency || "GBP" }}
                                {{ (product.receive_value || 0).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="product.validity_period"
                        class="text-xs text-green-300 mt-2"
                    >
                        Valid: {{ product.validity_period }}
                    </div>
                </button>
            </div>

            <div class="mt-6 flex justify-between">
                <button
                    @click="step = 2"
                    class="px-6 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                >
                    ← Back
                </button>
            </div>
        </div>

        <!-- Step 4: Serial Number -->
        <div
            v-if="step === 4"
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6"
        >
            <h2 class="text-lg font-bold text-white mb-1">
                Enter Serial Number
            </h2>
            <p class="text-sm text-dark-300 mb-5">
                Enter a serial number for your records. Must be authorized in
                the system.
            </p>

            <!-- Selected product summary -->
            <div class="bg-dark-700 rounded-xl p-4 mb-5">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-white">
                            {{
                                selectedProduct.display_text ||
                                selectedProduct.sku_code
                            }}
                        </div>
                        <div class="text-xs text-dark-400 mt-0.5">
                            SKU: {{ selectedProduct.sku_code }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-dark-400">You Pay</div>
                        <div class="text-lg font-bold text-yellow-400">
                            {{ selectedProduct.receive_currency || "GBP" }}
                            {{
                                (selectedProduct.receive_value || 0).toFixed(2)
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- How to redeem -->
            <div
                v-if="
                    selectedProduct.readmore_markdown ||
                    selectedProduct.description_markdown
                "
                class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-5"
            >
                <div class="text-xs text-blue-300 font-medium mb-1">
                    How to Redeem
                </div>
                <div
                    class="text-sm text-blue-200"
                    v-html="
                        selectedProduct.readmore_markdown ||
                        selectedProduct.description_markdown
                    "
                ></div>
            </div>

            <div>
                <label
                    class="block text-xs text-dark-400 uppercase tracking-wider mb-1"
                    >Serial Number</label
                >
                <input
                    v-model="serialNumber"
                    type="text"
                    class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white text-sm font-mono"
                    placeholder="Enter serial number for records"
                    @keyup.enter="checkAndConfirm"
                />
                <p class="text-xs text-dark-400 mt-1">
                    This number must be in the authorized list to proceed.
                </p>
            </div>

            <div class="mt-6 flex justify-between">
                <button
                    @click="step = 3"
                    class="px-6 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                >
                    ← Back
                </button>
                <button
                    @click="checkAndConfirm"
                    :disabled="!serialNumber.trim() || loading"
                    class="px-6 py-2.5 bg-primary text-white rounded-xl font-medium hover:bg-primary-dark transition disabled:opacity-50"
                >
                    {{ loading ? "Checking..." : "Continue →" }}
                </button>
            </div>
        </div>

        <!-- Step 5: Confirm -->
        <div
            v-if="step === 5"
            class="bg-dark-800 rounded-2xl border border-dark-600 p-6"
        >
            <h2 class="text-lg font-bold text-white mb-5">Confirm Purchase</h2>

            <div class="space-y-0 mb-6">
                <div class="flex justify-between py-3 border-b border-dark-600">
                    <span class="text-dark-300">Product</span>
                    <span class="text-white font-medium">{{
                        selectedProduct.display_text || selectedProduct.sku_code
                    }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-dark-600">
                    <span class="text-dark-300">Serial Number</span>
                    <span class="text-white font-mono">{{ serialNumber }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-dark-600">
                    <span class="text-dark-300">PIN Value</span>
                    <span class="text-white font-bold"
                        >{{ selectedProduct.receive_currency || "GBP" }}
                        {{
                            (selectedProduct.receive_value || 0).toFixed(2)
                        }}</span
                    >
                </div>
                <div class="flex justify-between py-3 border-b border-dark-600">
                    <span class="text-dark-300">You Pay</span>
                    <span class="text-yellow-400 font-bold text-lg"
                        >{{ selectedProduct.send_currency || "GBP" }}
                        {{
                            (
                                selectedProduct.send_value ||
                                selectedProduct.max_send_value ||
                                0
                            ).toFixed(2)
                        }}</span
                    >
                </div>
                <div class="flex justify-between py-3">
                    <span class="text-dark-300">Type</span>
                    <span class="text-yellow-300 font-medium"
                        >ReadReceipt — No mobile number needed</span
                    >
                </div>
            </div>

            <div
                class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4 mb-6"
            >
                <p class="text-sm text-yellow-300">
                    <strong>Note:</strong> After purchase you will receive a PIN
                    code. Any SIM of this provider can redeem it. Share the PIN
                    with your customer.
                </p>
            </div>

            <div class="flex justify-between">
                <button
                    @click="step = 4"
                    class="px-6 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                >
                    ← Back
                </button>
                <button
                    @click="confirmRecharge"
                    :disabled="loading"
                    class="px-8 py-2.5 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition disabled:opacity-50"
                >
                    {{ loading ? "Processing..." : "Complete Purchase" }}
                </button>
            </div>
        </div>
    </div>
</template>
