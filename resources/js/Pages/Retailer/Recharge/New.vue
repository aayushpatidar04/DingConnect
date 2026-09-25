<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import ProductCategories from "@/Components/ProductCategories.vue";
import { ref, onMounted, computed } from "vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    availableBalance: Number,
    countries: Array,
});

const currentStep = ref(1);
const selectedCountry = ref(null);
const mobileNumber = ref("");
const cleanedPhone = ref("");
const countryIso = ref("");
const phoneError = ref("");
const providers = ref([]);
const selectedProvider = ref(null);
const providerLive = ref(true);
const products = ref([]);
const promotions = ref([]);
const showPromotionsModal = ref(false);
const loadingProviders = ref(false);
const loadingProducts = ref(false);
const selectedProduct = ref(null);
const selectedSkuCode = ref("");
const isFreeRangeFlow = ref(false);
const freeRangeAmount = ref(0);
const freeRangePricing = ref(null);
const freeRangeLoading = ref(false);
const submitting = ref(false);
const errorMessage = ref("");
const showPinModal = ref(false);
const receiptText = ref("");
const receiptNumber = ref("");

const showReviewModal = ref(false);
const reviewTimestamp = ref("");
const reviewReference = ref("");

const form = useForm({
    mobile_number: "",
    country_id: "",
    operator_id: null,
    sku_code: "",
    send_value: 0,
    receive_value: 0,
    send_currency: "GBP",
    receive_currency: "GBP",
    display_text: "",
    default_display_text: "",
    validity_period: "",
    description_markdown: "",
    readmore_markdown: "",
    benefits: [],
    redemption_type: "Immediate",
    product_type: "",
    region_code: "",
    provider_code: "",
    free_range: false,
    receive_value_excluding_tax: 0,
});

const stepNames = ["Country", "Phone", "Operator", "Products", "Confirm"];
const totalSteps = stepNames.length;

const canProceedFromPhone = computed(
    () => cleanedPhone.value.length >= 7 && !phoneError.value,
);

const canProceedFromProvider = computed(
    () => !!selectedProvider.value && providerLive.value,
);

const currentCountryIso = computed(() => {
    if (countryIso.value) return countryIso.value;
    if (selectedCountry.value?.iso_code) return selectedCountry.value.iso_code;
    return "GB";
});

const showPromotionBadge = computed(() => promotions.value.length > 0);

// =========================================================================
// VALIDATION
// =========================================================================

function parseValidityPeriod(iso) {
    if (!iso || iso.trim() === "") return null;
    const match = iso.match(/P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?/);
    if (!match) return null;
    const parts = [];
    if (match[1]) parts.push(match[1] + "y");
    if (match[2]) parts.push(match[2] + "m");
    if (match[3]) parts.push(match[3] + "w");
    if (match[4]) parts.push(match[4] + "d");
    return parts.length ? "Valid for " + parts.join(" ") : null;
}

function validatePhone() {
    const cleaned = mobileNumber.value.replace(/\D/g, "");
    cleanedPhone.value = cleaned;

    if (cleaned.length < 7) {
        phoneError.value = "Please enter a valid phone number";
        return false;
    }

    // Prepend country calling code if not already present
    if (selectedCountry.value?.calling_code) {
        const code = selectedCountry.value.calling_code.replace(/\D/g, "");
        if (!cleaned.startsWith(code)) {
            const withoutCode = cleaned.startsWith("0")
                ? cleaned.slice(1)
                : cleaned;
            cleanedPhone.value = code + withoutCode;
        }
    }

    phoneError.value = "";
    return true;
}

// =========================================================================
// STEP NAVIGATION
// =========================================================================

function selectCountry(country) {
    selectedCountry.value = country;
    countryIso.value = country?.iso_code || "";
    currentStep.value = 2;
    phoneError.value = "";
    errorMessage.value = "";
}

function backToStep(step) {
    currentStep.value = step;
    errorMessage.value = "";
}

// =========================================================================
// PROVIDERS
// =========================================================================

async function proceedToProviders() {
    if (!validatePhone()) return;
    currentStep.value = 3;
    await loadProviders();
}

async function loadProviders() {
    loadingProviders.value = true;
    providers.value = [];
    selectedProvider.value = null;
    errorMessage.value = "";

    try {
        const params = new URLSearchParams();
        params.append("phone_number", cleanedPhone.value);
        params.append("country_iso", currentCountryIso.value);

        const res = await fetch(
            `/retailer/recharge/operators?${params.toString()}`,
        );
        const data = await res.json();
        if (data.success) {
            countryIso.value = data.country_iso; // Sync with API-detected country
            providers.value = data.providers || [];

            if (providers.value.length === 0) {
                errorMessage.value =
                    "No operators available for this phone number.";
            }
        } else {
            errorMessage.value = data.error || "Failed to load operators.";
        }
    } catch (e) {
        errorMessage.value = "Network error. Please try again.";
    } finally {
        loadingProviders.value = false;
    }
}

async function selectProvider(provider) {
    selectedProvider.value = provider;
    form.operator_id = provider.id;
    form.provider_code = provider.provider_code;
    errorMessage.value = "";
}

async function proceedToProducts() {
    if (!selectedProvider.value) return;
    errorMessage.value = "";

    // Check provider status
    try {
        const res = await fetch(
            `/retailer/recharge/provider-status?provider_code=${selectedProvider.value.provider_code}`,
        );
        const data = await res.json();
        providerLive.value = data.is_live !== false;

        if (!providerLive.value) {
            errorMessage.value =
                "Selected provider is down at this moment, please try after some time.";
            return;
        }
    } catch (e) {
        providerLive.value = true;
    }

    await loadProducts();
    currentStep.value = 4;
}

async function loadProducts() {
    loadingProducts.value = true;
    products.value = [];
    selectedProduct.value = null;
    errorMessage.value = "";

    try {
        const params = new URLSearchParams();
        params.append("account_number", cleanedPhone.value);
        params.append("country_iso", currentCountryIso.value);
        if (selectedProvider.value)
            params.append(
                "provider_code",
                selectedProvider.value.provider_code,
            );

        const res = await fetch(
            `/retailer/recharge/products?${params.toString()}`,
        );
        const data = await res.json();
        if (data.success) {
            products.value = data.products || [];
            await loadPromotions();
        } else {
            errorMessage.value = data.error || "Failed to load products.";
        }
    } catch (e) {
        errorMessage.value = "Network error. Please try again.";
    } finally {
        loadingProducts.value = false;
    }
}

// =========================================================================
// PROMOTIONS
// =========================================================================

async function loadPromotions() {
    try {
        const params = new URLSearchParams();
        params.append("country_isos", currentCountryIso.value);
        if (selectedProvider.value)
            params.append(
                "provider_codes",
                selectedProvider.value.provider_code,
            );

        const res = await fetch(
            `/retailer/recharge/promotions?${params.toString()}`,
        );
        const data = await res.json();
        if (data.success && data.promotions && data.promotions.length > 0) {
            promotions.value = data.promotions;
            showPromotionsModal.value = true;
        }
    } catch (e) {
        promotions.value = [];
    }
}

function closePromotionsModal() {
    showPromotionsModal.value = false;
}

// =========================================================================
// PRODUCT SELECTION → CONFIRM
// =========================================================================

function selectProduct(product) {
    selectedProduct.value = product;
    selectedSkuCode.value = product.sku_code;
    isFreeRangeFlow.value = !product.is_denomination;

    if (isFreeRangeFlow.value) {
        freeRangeAmount.value = product.min_send_value || 5;
    }

    form.sku_code = product.sku_code;
    form.send_value = product.send_value;
    form.receive_value = product.receive_value;
    form.send_currency = product.send_currency;
    form.receive_currency = product.receive_currency;
    form.display_text = product.display_text;
    form.default_display_text = product.display_text;
    form.validity_period = product.validity_period;
    form.description_markdown = product.description_markdown || "";
    form.readmore_markdown = product.readmore_markdown || "";
    form.benefits = product.benefits || [];
    form.redemption_type = product.redemption_type || "Immediate";
    form.product_type = product.product_type || "";
    form.region_code = "";
    form.provider_code = selectedProvider.value?.provider_code || "";
    form.receive_value_excluding_tax =
        product.receive_value_excluding_tax || product.receive_value || 0;
    form.free_range = isFreeRangeFlow.value;

    currentStep.value = 5;
}

function backToProducts() {
    selectedProduct.value = null;
    selectedSkuCode.value = "";
    isFreeRangeFlow.value = false;
    freeRangePricing.value = null;
    form.sku_code = "";
    currentStep.value = 4;
}

// =========================================================================
// FREE RANGE PRICING
// =========================================================================

async function fetchFreeRangePricing() {
    if (!freeRangeAmount.value || freeRangeAmount.value <= 0) {
        freeRangePricing.value = null;
        return;
    }

    const min = selectedProduct.value?.min_send_value || 1;
    const max = selectedProduct.value?.max_send_value || 1000;

    if (freeRangeAmount.value < min || freeRangeAmount.value > max) {
        freeRangePricing.value = null;
        return;
    }

    freeRangeLoading.value = true;
    try {
        const params = new URLSearchParams();
        params.append("sku_code", selectedProduct.value.sku_code);
        params.append("send_value", freeRangeAmount.value);
        params.append("send_currency_iso", form.send_currency);

        const res = await fetch(
            `/retailer/recharge/pricing?${params.toString()}`,
        );
        const data = await res.json();

        if (data.success && data.pricing) {
            freeRangePricing.value = data.pricing;
            form.send_value = data.pricing.send_value;
            form.receive_value = data.pricing.receive_value;
            form.receive_value_excluding_tax =
                data.pricing.receive_value_excluding_tax;
        } else {
            freeRangePricing.value = null;
        }
    } catch (e) {
        freeRangePricing.value = null;
    } finally {
        freeRangeLoading.value = false;
    }
}

// =========================================================================
// SUBMIT
// =========================================================================

// Shared pre-submit validation
function validateBeforeSubmit() {
    errorMessage.value = "";

    if (!selectedProduct.value) {
        errorMessage.value = "Please select a product";
        return false;
    }

    if (isFreeRangeFlow.value && !freeRangePricing.value) {
        errorMessage.value = "Please enter a valid amount";
        return false;
    }

    if (props.availableBalance < form.send_value) {
        errorMessage.value =
            "Insufficient wallet balance. Please top up first.";
        return false;
    }

    return true;
}

function submitRecharge(action = "buy") {
    if (action === "review") {
        if (!validateBeforeSubmit()) return;
        reviewTimestamp.value = new Date().toLocaleString();
        reviewReference.value = "RCPT-" + Date.now(); // client-side reference only
        showReviewModal.value = true;
        return;
    }

    // action === 'buy'
    if (!validateBeforeSubmit()) return;
    showReviewModal.value = false;
    doSubmit();
}

function cancelReview() {
    showReviewModal.value = false;
}

function doSubmit() {
    submitting.value = true;
    form.mobile_number = cleanedPhone.value;
    form.country_id = selectedCountry.value.id;

    if (selectedProvider.value) {
        form.operator_id = selectedProvider.value.id;
        form.provider_code = selectedProvider.value.provider_code;
    }

    form.post("/retailer/recharge", {
        onSuccess: () => {
            showReviewModal.value = false;
            if (selectedProduct.value.redemption_type === "ReadReceipt") {
                showPinModal.value = true;
            }
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || "Recharge failed";
            submitting.value = false;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function closePinModal() {
    showPinModal.value = false;
    receiptText.value = "";
    receiptNumber.value = "";
}

function copyPin() {
    navigator.clipboard.writeText(receiptText.value);
}

function resetFlow() {
    currentStep.value = 1;
    selectedCountry.value = null;
    mobileNumber.value = "";
    cleanedPhone.value = "";
    countryIso.value = "";
    providers.value = [];
    selectedProvider.value = null;
    products.value = [];
    selectedProduct.value = null;
    selectedSkuCode.value = "";
    isFreeRangeFlow.value = false;
    freeRangeAmount.value = 0;
    freeRangePricing.value = null;
    promotions.value = [];
    errorMessage.value = "";
    phoneError.value = "";
}

// =========================================================================
// REAL-TIME LISTENERS
// =========================================================================

onMounted(() => {
    if (props.auth?.user && window.Echo?.private) {
        window.Echo.private(`retailer.${props.auth.user.id}`).listen(
            ".RechargeSuccess",
            (e) => {
                if (e.receipt_text) {
                    receiptText.value = e.receipt_text;
                    receiptNumber.value = e.receipt_number || "";
                    showPinModal.value = true;
                }
            },
        );
    }
});
</script>

<template>
    <Head title="New Recharge - MK Network" />
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white mb-1">New Recharge</h1>
            <p class="text-dark-300">
                Instant mobile top-up powered by DingConnect
            </p>
        </div>

        <!-- PIN Recharge Option -->
        <a
            href="/retailer/recharge/pin"
            class="block bg-yellow-500/10 border border-yellow-500/30 rounded-2xl p-5 mb-6 hover:bg-yellow-500/20 transition group"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">🎫</span>
                    </div>
                    <div>
                        <div class="font-semibold text-yellow-300 text-lg group-hover:text-yellow-200">
                            PIN Voucher Recharge
                        </div>
                        <div class="text-sm text-yellow-400/70">
                            No mobile number required. Purchase a PIN voucher and share it with your customer.
                        </div>
                    </div>
                </div>
                <span class="text-yellow-400 group-hover:translate-x-1 transition">→</span>
            </div>
        </a>

        <!-- Wallet Balance Alert -->
        <div
            v-if="availableBalance < 10"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 mb-6 flex items-start"
        >
            <p class="text-sm text-red-300">
                Low wallet balance.
                <a
                    href="/retailer/wallet"
                    class="underline font-medium hover:text-red-200"
                    >Top up now &rarr;</a
                >
            </p>
        </div>

        <!-- Stepper -->
        <div class="mb-8 overflow-x-auto">
            <div class="flex items-center justify-between min-w-max">
                <template v-for="(step, idx) in stepNames" :key="idx">
                    <div class="flex flex-col items-center">
                        <div
                            :class="[
                                'w-9 h-9 rounded-full flex items-center justify-center font-semibold text-xs transition',
                                currentStep > idx + 1
                                    ? 'bg-green-500 text-white'
                                    : currentStep === idx + 1
                                      ? 'bg-primary text-white'
                                      : 'bg-dark-700 text-dark-400 border border-dark-600',
                            ]"
                        >
                            <span v-if="currentStep > idx + 1">&#10003;</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                        <div
                            :class="[
                                'text-[10px] mt-1 font-medium whitespace-nowrap',
                                currentStep >= idx + 1
                                    ? 'text-white'
                                    : 'text-dark-400',
                            ]"
                        >
                            {{ step }}
                        </div>
                    </div>
                    <div
                        v-if="idx < stepNames.length - 1"
                        :class="[
                            'flex-1 h-0.5 mx-1 mb-4 transition min-w-[20px]',
                            currentStep > idx + 1
                                ? 'bg-green-500'
                                : 'bg-dark-700',
                        ]"
                    ></div>
                </template>
            </div>
        </div>

        <!-- Error Message -->
        <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6 text-red-300 text-sm"
        >
            {{ errorMessage }}
        </div>

        <!-- =================================================================
             STEP 1: COUNTRY
             ================================================================= -->
        <div v-if="currentStep === 1">
            <h2 class="text-xl font-semibold text-white mb-4">
                Select Country
            </h2>
            <p class="text-sm text-dark-300 mb-4">
                Choose the destination country for this recharge.
            </p>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <button
                    v-for="country in countries"
                    :key="country.id"
                    @click="selectCountry(country)"
                    class="bg-dark-800 hover:bg-dark-700 border border-dark-600 hover:border-primary rounded-2xl p-5 text-left transition"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-3xl text-primary">{{
                            country.flag_emoji || "🌍"
                        }}</span>
                        <div>
                            <div class="font-semibold text-white">
                                {{ country.name }}
                            </div>
                            <div class="text-xs text-dark-400">
                                {{ country.iso_code }} · +{{
                                    country.calling_code
                                }}
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- =================================================================
             STEP 2: PHONE NUMBER
             ================================================================= -->
        <div v-if="currentStep === 2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">
                    Enter Phone Number
                </h2>
                <button
                    @click="backToStep(1)"
                    class="text-sm text-dark-300 hover:text-white"
                >
                    &larr; Change country
                </button>
            </div>

            <div class="bg-dark-800 border border-dark-600 rounded-2xl p-6">
                <label class="text-sm text-dark-300 mb-2 block"
                    >Mobile Number ({{ selectedCountry?.name }})</label
                >
                <div
                    class="flex items-center bg-dark-700 border border-dark-600 rounded-xl overflow-hidden"
                >
                    <span
                        class="px-4 py-3 text-white font-semibold border-r border-dark-600"
                    >
                        +{{ selectedCountry?.calling_code }}
                    </span>
                    <input
                        v-model="mobileNumber"
                        type="tel"
                        placeholder="Enter phone number"
                        class="flex-1 bg-transparent px-4 py-3 text-white outline-none"
                        @input="validatePhone"
                    />
                </div>
                <p v-if="phoneError" class="text-xs text-red-400 mt-2">
                    {{ phoneError }}
                </p>
                <p class="text-xs text-dark-400 mt-2">
                    Country code will be added automatically
                </p>

                <button
                    @click="proceedToProviders"
                    :disabled="!canProceedFromPhone"
                    class="mt-6 w-full btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Continue &rarr;
                </button>
            </div>
        </div>

        <!-- =================================================================
             STEP 3: OPERATOR SELECTION
             ================================================================= -->
        <div v-if="currentStep === 3">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">
                    Select Operator
                </h2>
                <button
                    @click="backToStep(2)"
                    class="text-sm text-dark-300 hover:text-white"
                >
                    &larr; Change phone
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loadingProviders" class="text-center py-16">
                <div
                    class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"
                ></div>
                <p class="mt-4 text-dark-300">Detecting operator...</p>
            </div>

            <!-- No providers found -->
            <div
                v-else-if="providers.length === 0 && !loadingProviders"
                class="text-center py-16"
            >
                <p class="text-dark-300 text-lg mb-2">
                    No operators found for this number.
                </p>
                <p class="text-dark-400 text-sm mb-4">
                    Try a different number or country.
                </p>
                <button
                    @click="backToStep(2)"
                    class="btn-primary text-white px-6 py-2 rounded-xl text-sm"
                >
                    &larr; Change number
                </button>
            </div>

            <!-- Provider list -->
            <div v-else>
                <p class="text-sm text-dark-300 mb-4">
                    Available operators for +{{ cleanedPhone }}:
                </p>
                <div
                    :class="[
                        'grid gap-4',
                        providers.length === 1
                            ? 'grid-cols-1 max-w-md'
                            : 'grid-cols-2 md:grid-cols-3',
                    ]"
                >
                    <button
                        v-for="provider in providers"
                        :key="provider.provider_code"
                        @click="selectProvider(provider)"
                        :class="[
                            'rounded-2xl p-5 text-left transition relative',
                            selectedProvider?.provider_code ===
                            provider.provider_code
                                ? 'bg-primary/20 border-2 border-primary'
                                : 'bg-dark-800 border border-dark-600 hover:border-primary/50',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                v-if="provider.logo_url"
                                :src="provider.logo_url"
                                :alt="provider.name"
                                class="w-12 h-12 rounded-lg object-contain bg-white p-1"
                            />
                            <div>
                                <div class="text-lg font-semibold text-white">
                                    {{ provider.name }}
                                </div>
                                <div class="text-xs text-dark-400 mt-1">
                                    {{ provider.provider_code }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="
                                selectedProvider?.provider_code ===
                                provider.provider_code
                            "
                            class="text-xs text-primary mt-2 font-semibold"
                        >
                            &#10003; Selected
                        </div>
                    </button>
                </div>

                <button
                    @click="proceedToProducts"
                    :disabled="!canProceedFromProvider"
                    class="mt-6 w-full btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Continue &rarr;
                </button>
            </div>
        </div>

        <!-- =================================================================
             STEP 4: PRODUCTS (categorized)
             ================================================================= -->
        <div v-if="currentStep === 4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Select Product</h2>
                <button
                    @click="backToStep(3)"
                    class="text-sm text-dark-300 hover:text-white"
                >
                    &larr; Change operator
                </button>
            </div>

            <!-- Promotions Badge -->
            <div v-if="showPromotionBadge" class="mb-4">
                <button
                    @click="showPromotionsModal = true"
                    class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl px-4 py-2 text-yellow-300 text-sm hover:bg-yellow-500/20"
                >
                    &#127873; {{ promotions.length }} promotion{{
                        promotions.length > 1 ? "s" : ""
                    }}
                    available
                </button>
            </div>

            <!-- Product Categories Component -->
            <ProductCategories
                :products="products"
                :loading="loadingProducts"
                @select-product="selectProduct"
            />

            <div
                v-if="!loadingProducts && products.length === 0"
                class="text-center py-12"
            >
                <p class="text-dark-300">
                    No products available for this operator.
                </p>
            </div>
        </div>

        <!-- =================================================================
             STEP 5: CONFIRM / REVIEW
             ================================================================= -->
        <div v-if="currentStep === 5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">
                    {{ isFreeRangeFlow ? "Enter Amount" : "Review Order" }}
                </h2>
                <button
                    @click="backToProducts"
                    class="text-sm text-dark-300 hover:text-white"
                >
                    &larr; Back to products
                </button>
            </div>

            <div class="bg-dark-800 border border-dark-600 rounded-2xl p-6">
                <!-- Free Range Input -->
                <div v-if="isFreeRangeFlow" class="mb-6">
                    <label class="text-sm text-dark-300 mb-2 block">
                        Enter Amount ({{ selectedProduct?.send_currency }})
                    </label>
                    <input
                        v-model.number="freeRangeAmount"
                        @input="fetchFreeRangePricing"
                        type="number"
                        :min="selectedProduct?.min_send_value"
                        :max="selectedProduct?.max_send_value"
                        class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white text-lg outline-none"
                    />
                    <p class="text-xs text-dark-400 mt-2">
                        Min: {{ selectedProduct?.send_currency }}
                        {{ selectedProduct?.min_send_value }} &middot; Max:
                        {{ selectedProduct?.send_currency }}
                        {{ selectedProduct?.max_send_value }}
                    </p>

                    <div v-if="freeRangeLoading" class="mt-4 text-center">
                        <div
                            class="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"
                        ></div>
                        <span class="ml-2 text-dark-300">Calculating...</span>
                    </div>

                    <div
                        v-else-if="freeRangePricing"
                        class="mt-4 bg-primary/10 border border-primary/30 rounded-xl p-4"
                    >
                        <div class="text-sm text-dark-300 mb-1">
                            Customer will receive:
                        </div>
                        <div class="text-2xl font-bold text-primary-light">
                            {{ freeRangePricing.receive_currency }}
                            {{ freeRangePricing.receive_value.toFixed(2) }}
                        </div>
                        <div
                            v-if="
                                freeRangePricing.receive_value !==
                                freeRangePricing.receive_value_excluding_tax
                            "
                            class="text-xs text-dark-400 mt-2"
                        >
                            (excluding tax:
                            {{ freeRangePricing.receive_currency }}
                            {{
                                freeRangePricing.receive_value_excluding_tax.toFixed(
                                    2,
                                )
                            }})
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Country</span>
                        <span class="text-white"
                            >{{ selectedCountry?.name }} ({{
                                currentCountryIso
                            }})</span
                        >
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Phone</span>
                        <span class="text-white">+{{ cleanedPhone }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Operator</span>
                        <span class="text-white">{{
                            selectedProvider?.name
                        }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Product</span>
                        <span class="text-white">{{
                            selectedProduct?.display_text || selectedSkuCode
                        }}</span>
                    </div>
                    <div
                        v-if="selectedProduct?.validity_period"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-dark-300">Validity</span>
                        <span class="text-green-400">{{
                            parseValidityPeriod(selectedProduct.validity_period)
                        }}</span>
                    </div>
                    <div class="border-t border-dark-600 pt-3 mt-3">
                        <div class="flex justify-between items-baseline">
                            <span class="text-dark-300">You Pay</span>
                            <span class="text-2xl font-bold text-white">
                                {{ form.send_currency }}
                                {{ form.send_value.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-dark-300">Customer Gets</span>
                            <span class="text-green-400">
                                {{ form.receive_currency }}
                                {{ form.receive_value.toFixed(2) }}
                            </span>
                        </div>
                        <div
                            v-if="
                                form.receive_value !==
                                form.receive_value_excluding_tax
                            "
                            class="flex justify-between text-xs mt-1 text-dark-400"
                        >
                            <span>Excl. tax</span>
                            <span
                                >{{ form.receive_currency }}
                                {{
                                    form.receive_value_excluding_tax.toFixed(2)
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div
                    v-if="selectedProduct?.description_markdown"
                    class="mb-4 text-sm text-dark-300 prose prose-invert max-w-none"
                >
                    <div v-html="selectedProduct.description_markdown"></div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="submitRecharge('review')"
                        :disabled="
                            submitting || (isFreeRangeFlow && !freeRangePricing)
                        "
                        class="flex-1 border border-dark-600 text-white py-3 rounded-xl font-semibold hover:bg-dark-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Review Order
                    </button>
                    <button
                        @click="submitRecharge('buy')"
                        :disabled="
                            submitting || (isFreeRangeFlow && !freeRangePricing)
                        "
                        class="flex-1 btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ submitting ? "Processing..." : "Buy Now" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================
             PROMOTIONS MODAL
             ================================================================= -->
        <div
            v-if="showPromotionsModal"
            @click.self="closePromotionsModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-dark-800 border border-dark-600 rounded-2xl max-w-md w-full p-6"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">
                        &#127873; Available Promotions
                    </h3>
                    <button
                        @click="closePromotionsModal"
                        class="text-dark-400 hover:text-white"
                    >
                        &times;
                    </button>
                </div>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <div
                        v-for="promo in promotions"
                        :key="promo.localization_key"
                        class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4"
                    >
                        <div class="font-semibold text-yellow-300 mb-1">
                            {{ promo.promotion_name }}
                        </div>
                        <div class="text-sm text-yellow-200">
                            {{ promo.display_text }}
                        </div>
                        <div
                            v-if="promo.from_date || promo.to_date"
                            class="text-xs text-yellow-400 mt-2"
                        >
                            {{ promo.from_date }}
                            {{ promo.from_date && promo.to_date ? "to" : "" }}
                            {{ promo.to_date }}
                        </div>
                    </div>
                </div>
                <button
                    @click="closePromotionsModal"
                    class="mt-6 w-full btn-primary text-white py-3 rounded-xl font-semibold"
                >
                    Got it
                </button>
            </div>
        </div>

        <!-- =================================================================
            REVIEW / RECEIPT CONFIRMATION MODAL
            ================================================================= -->
        <div
            v-if="showReviewModal"
            @click.self="cancelReview"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-dark-800 border border-dark-600 rounded-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto"
            >
                <!-- Receipt header -->
                <div
                    class="text-center border-b border-dashed border-dark-600 pb-4 mb-4"
                >
                    <h3 class="text-lg font-bold text-white">
                        Recharge Receipt
                    </h3>
                    <p class="text-xs text-dark-400 mt-1">
                        Please review before proceeding
                    </p>
                    <p class="text-xs text-dark-400 font-mono mt-1">
                        {{ reviewReference }}
                    </p>
                    <p class="text-xs text-dark-400">{{ reviewTimestamp }}</p>
                </div>

                <!-- Receipt details -->
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-dark-300">Country</span>
                        <span class="text-white"
                            >{{ selectedCountry?.name }} ({{
                                currentCountryIso
                            }})</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-dark-300">Phone</span>
                        <span class="text-white">+{{ cleanedPhone }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-dark-300">Operator</span>
                        <span class="text-white">{{
                            selectedProvider?.name
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-dark-300">Product</span>
                        <span class="text-white">{{
                            selectedProduct?.display_text || selectedSkuCode
                        }}</span>
                    </div>
                    <div
                        v-if="selectedProduct?.validity_period"
                        class="flex justify-between"
                    >
                        <span class="text-dark-300">Validity</span>
                        <span class="text-green-400">{{
                            parseValidityPeriod(selectedProduct.validity_period)
                        }}</span>
                    </div>
                </div>

                <!-- Amounts -->
                <div class="border-t border-dashed border-dark-600 pt-3 mb-4">
                    <div class="flex justify-between items-baseline mb-1">
                        <span class="text-dark-300">You Pay</span>
                        <span class="text-xl font-bold text-white">
                            {{ form.send_currency }}
                            {{ form.send_value.toFixed(2) }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-dark-300">Customer Gets</span>
                        <span class="text-green-400">
                            {{ form.receive_currency }}
                            {{ form.receive_value.toFixed(2) }}
                        </span>
                    </div>
                    <div
                        v-if="
                            form.receive_value !==
                            form.receive_value_excluding_tax
                        "
                        class="flex justify-between text-xs text-dark-400"
                    >
                        <span>Excl. tax</span>
                        <span
                            >{{ form.receive_currency }}
                            {{
                                form.receive_value_excluding_tax.toFixed(2)
                            }}</span
                        >
                    </div>
                </div>

                <!-- Description -->
                <div
                    v-if="selectedProduct?.description_markdown"
                    class="mb-4 text-xs text-dark-300 prose prose-invert max-w-none"
                >
                    <div v-html="selectedProduct.description_markdown"></div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button
                        @click="cancelReview"
                        :disabled="submitting"
                        class="flex-1 border border-dark-600 text-white py-3 rounded-xl font-semibold hover:bg-dark-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitRecharge('buy')"
                        :disabled="submitting"
                        class="flex-1 btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{
                            submitting
                                ? "Processing..."
                                : "Proceed with Recharge"
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================
             PIN / VOUCHER MODAL
             ================================================================= -->
        <div
            v-if="showPinModal"
            @click.self="closePinModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-dark-800 border border-green-500/30 rounded-2xl max-w-md w-full p-6"
            >
                <div class="text-center">
                    <div
                        class="w-12 h-12 mx-auto bg-green-500/20 rounded-full flex items-center justify-center mb-4"
                    >
                        &#10003;
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">
                        Recharge Successful!
                    </h3>
                    <p class="text-sm text-dark-300 mb-4">
                        Please share this PIN with your customer
                    </p>
                </div>
                <div class="bg-dark-700 rounded-xl p-4 mb-4">
                    <div class="text-xs text-dark-400 mb-1">Receipt Number</div>
                    <div class="text-white font-mono text-sm mb-3">
                        {{ receiptNumber }}
                    </div>
                    <div class="text-xs text-dark-400 mb-1">
                        PIN / Voucher Code
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            class="flex-1 bg-dark-900 rounded-lg p-3 border border-primary/30 text-primary-light font-bold break-all"
                            v-html="receiptText.replace(/\n/g, '<br>')"
                        ></div>
                        <button
                            @click="copyPin"
                            class="text-xs bg-dark-700 px-3 py-2 rounded text-dark-300 hover:text-white transition whitespace-nowrap"
                        >
                            📋 Copy
                        </button>
                    </div>
                </div>
                <button
                    @click="closePinModal"
                    class="w-full btn-primary text-white py-3 rounded-xl font-semibold"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>
