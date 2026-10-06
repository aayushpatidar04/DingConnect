<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import ProductCategories from "@/Components/ProductCategories.vue";
import { ref, computed, onMounted } from "vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    availableBalance: Number,
    countries: Array,
});

const currentStep = ref(1);
const selectedCountry = ref(null);
const countryIso = ref("");
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
const mobileNumber = ref("");
const cleanedPhone = ref("");
const submitting = ref(false);
const errorMessage = ref("");

// Dual-API state
const vtProducts = ref([]);
const selectedProductSource = ref("ding"); // "ding" or "valuetopup"
const valuetopupSkuId = ref(null);

// Product description (fetched on demand)
const productDescription = ref("");
const productReadmore = ref("");
const loadingDescription = ref(false);

// Review modal
const showReviewModal = ref(false);
const reviewTimestamp = ref("");
const reviewReference = ref("");

// PIN modal
const showPinModal = ref(false);
const receiptText = ref("");
const receiptNumber = ref("");

const stepNames = ["Country", "Provider", "Products", "Number", "Confirm"];
const totalSteps = stepNames.length;

const currentCountryIso = computed(() => {
    if (countryIso.value) return countryIso.value;
    if (selectedCountry.value?.iso_code) return selectedCountry.value.iso_code;
    return "GB";
});

const isPinProduct = computed(
    () => selectedProduct.value?.redemption_type === "ReadReceipt",
);

const showPromotionBadge = computed(() => promotions.value.length > 0);

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
    gateway: "ding",
    valuetopup_sku_id: null,
});

// =========================================================================
// VALIDATION HELPERS
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

const phoneError = ref("");

const canProceedFromPhone = computed(
    () => cleanedPhone.value.length >= 7 && !phoneError.value,
);

// Merged product list for display
const allProducts = computed(() => {
    const map = new Map();
    for (const p of products.value) {
        map.set(p.sku_code, { ...p, _source: "ding" });
    }
    for (const p of vtProducts.value) {
        map.set(p.sku_code, { ...p, _source: "valuetopup" });
    }
    return [...map.values()];
});

// =========================================================================
// STEP NAVIGATION
// =========================================================================

function selectCountry(country) {
    selectedCountry.value = country;
    countryIso.value = country?.iso_code || "";
    currentStep.value = 2;
    providers.value = [];
    selectedProvider.value = null;
    errorMessage.value = "";

    loadProviders();
}

function backToStep(step) {
    currentStep.value = step;
    errorMessage.value = "";
}

// =========================================================================
// PROVIDERS (Step 2)
// =========================================================================

async function loadProviders() {
    loadingProviders.value = true;
    providers.value = [];
    selectedProvider.value = null;
    errorMessage.value = "";

    // Fire both requests in parallel
    const [dingRes, vtRes] = await Promise.allSettled([
        fetch(
            `/retailer/recharge/providers?country_iso=${currentCountryIso.value}`,
        ).then((r) => r.json()),
        fetch(
            `/retailer/recharge/valuetopup/operators?country_iso=${currentCountryIso.value}`,
        ).then((r) => r.json()),
    ]);

    const dingProviders =
        dingRes.status === "fulfilled" && dingRes.value.success
            ? dingRes.value.providers || []
            : [];

    const vtProviders =
        vtRes.status === "fulfilled" && vtRes.value.success
            ? vtRes.value.providers || []
            : [];

    // Step 1: Start with DingConnect providers, default valuetopup_id to null
    const merged = dingProviders.map((p) => ({
        ...p,
        valuetopup_id: null,
    }));

    // Step 2: For each VT provider, either:
    //   - update the matching Ding row's valuetopup_id (if already linked), OR
    //   - append it as a VT-only row (if not in Ding)
    for (const vt of vtProviders) {
        const vtId = vt.valuetopup_id;
        if (vt.source === "both") {
            // Linked to an existing Ding row — update that row
            const idx = merged.findIndex(
                (p) => p.provider_id === vt.provider_id,
            );
            if (idx !== -1) {
                merged[idx].valuetopup_id = vtId;
            }
        } else if (vt.source === "valuetopup_only") {
            // New VT-only provider — add to the merged list
            merged.push({
                provider_id: vt.provider_id,
                provider_code: vt.provider_code, // already "vt-<id>"
                name: vt.name,
                logo_url: vt.logo_url,
                country_iso: vt.country_iso,
                valuetopup_id: vtId,
                source: "valuetopup_only",
            });
        }
    }

    providers.value = merged;

    if (providers.value.length === 0) {
        errorMessage.value = "No operators available for this country.";
    }

    loadingProviders.value = false;
}

async function selectProvider(provider) {
    selectedProvider.value = provider;
    form.provider_code = provider.provider_code;
    selectedProductSource.value = provider.source === "valuetopup_only" ? "valuetopup" : "ding";
    errorMessage.value = "";
}

async function proceedToProducts() {
    if (!selectedProvider.value) return;
    errorMessage.value = "";
    submitting.value = true;

    // Skip DingConnect provider-status for VT-only providers
    if (selectedProvider.value.source !== "valuetopup_only") {
        try {
            const res = await fetch(
                `/retailer/recharge/provider-status?provider_code=${selectedProvider.value.provider_code}`,
            );
            const data = await res.json();
            providerLive.value = data.is_live !== false;
            if (!providerLive.value) {
                errorMessage.value =
                    "Selected provider is down at this moment, please try after some time.";
                submitting.value = false;
                return;
            }
        } catch (e) {
            providerLive.value = true;
        }
    } else {
        providerLive.value = true;
    }

    // Flip to Step 3 first so the product shimmer is visible
    // during the long load (DingConnect + Valuetopup SKUs).
    currentStep.value = 3;
    await loadProducts();
    submitting.value = false;
}

async function loadProducts() {
    loadingProducts.value = true;
    products.value = [];
    selectedProduct.value = null;
    productDescription.value = "";
    productReadmore.value = "";
    errorMessage.value = "";

    const provider = selectedProvider.value;
    const country = currentCountryIso.value;

    // Single merged endpoint: DingConnect products + Valuetopup products
    // (backend handles VT chain + dedup; Ding takes priority)
    const params = new URLSearchParams();
    params.append("country_iso", country);
    params.append("provider_code", provider.provider_code);
    // Pass valuetopup_id so backend can also fetch VT products
    if (provider.valuetopup_id || provider.source === "valuetopup_only") {
        params.append("valuetopup_id", provider.valuetopup_id || provider.provider_id);
    }

    try {
        const res = await fetch(
            `/retailer/recharge/products?${params.toString()}`,
        );
        const data = await res.json();

        if (data.success) {
            const dingItems = [];
            const vtItems = [];

            for (const p of (data.products || [])) {
                if (p._source === "valuetopup") {
                    vtItems.push(p);
                } else {
                    dingItems.push(p);
                }
            }

            products.value = dingItems;
            vtProducts.value = vtItems;

            if (products.value.length === 0 && vtProducts.value.length === 0) {
                errorMessage.value = "No products available for this operator.";
            }
        } else {
            errorMessage.value = data.error || "Failed to load products.";
        }
    } catch (e) {
        errorMessage.value = "Network error. Please try again.";
    } finally {
        loadingProducts.value = false;
    }

    await loadPromotions();
}

async function loadPromotions() {
    try {
        const params = new URLSearchParams();
        params.append("country_isos", currentCountryIso.value);
        params.append("provider_codes", selectedProvider.value.provider_code);

        const res = await fetch(
            `/retailer/recharge/promotions?${params.toString()}`,
        );
        const data = await res.json();
        if (data.success && data.promotions && data.promotions.length > 0) {
            promotions.value = data.promotions;
        }
    } catch (e) {
        promotions.value = [];
    }
}

function closePromotionsModal() {
    showPromotionsModal.value = false;
}

// =========================================================================
// PRODUCT SELECTION (Step 3 → 4/5)
// =========================================================================

async function selectProduct(product) {
    selectedProduct.value = product;
    selectedSkuCode.value = product.sku_code;
    isFreeRangeFlow.value = !product.is_denomination;
    selectedProductSource.value = product._source || "ding";

    // Set gateway based on source
    if (product._source === "valuetopup") {
        form.gateway = "valuetopup";
        // Extract VT sku id from the "vt-XXXX" code
        const rawCode = product.sku_code.replace(/^vt-/, "");
        form.valuetopup_sku_id = parseInt(rawCode) || null;
        valuetopupSkuId.value = form.valuetopup_sku_id;
    } else {
        form.gateway = "ding";
        form.valuetopup_sku_id = null;
        valuetopupSkuId.value = null;
    }

    if (isFreeRangeFlow.value) {
        freeRangeAmount.value = product.min_send_value || 5;
        freeRangePricing.value = null;
    }

    // Reset description
    productDescription.value = "";
    productReadmore.value = "";

    // Fetch product description from API (DingConnect descriptions only)
    if (form.gateway === "ding") {
        await fetchProductDescription(product.sku_code);
    }

    // Populate form
    form.sku_code = product.sku_code;
    form.send_value = product.send_value;
    form.receive_value = product.receive_value;
    form.send_currency = product.send_currency;
    form.receive_currency = product.receive_currency;
    form.display_text = product.display_text;
    form.default_display_text = product.display_text;
    form.validity_period = product.validity_period;
    form.description_markdown = productDescription.value || product.description_markdown || "";
    form.readmore_markdown = productReadmore.value || product.readmore_markdown || "";
    form.benefits = product.benefits || [];
    form.redemption_type = product.redemption_type || "Immediate";
    form.product_type = product.product_type || "";
    form.region_code = "";
    form.provider_code = selectedProvider.value?.provider_code || "";
    form.receive_value_excluding_tax =
        product.receive_value_excluding_tax || product.receive_value || 0;
    form.free_range = isFreeRangeFlow.value;

    // PIN products skip the number step
    if (isPinProduct.value) {
        currentStep.value = 5; // go straight to confirm
    } else {
        currentStep.value = 4; // ask for number
    }
}

async function fetchProductDescription(skuCode) {
    if (!skuCode) return;
    loadingDescription.value = true;
    try {
        const res = await fetch(
            `/retailer/recharge/product-description?sku_code=${skuCode}`,
        );
        const data = await res.json();
        if (data.success) {
            productDescription.value = data.description_markdown || "";
            productReadmore.value = data.readmore_markdown || "";
        }
    } catch (e) {
        console.error("Failed to fetch product description", e);
    } finally {
        loadingDescription.value = false;
    }
}

function backToProducts() {
    selectedProduct.value = null;
    selectedSkuCode.value = "";
    isFreeRangeFlow.value = false;
    freeRangePricing.value = null;
    productDescription.value = "";
    productReadmore.value = "";
    currentStep.value = 3;
}

// =========================================================================
// NUMBER INPUT (Step 4 — only for Immediate/Top-up)
// =========================================================================

const validatingNumber = ref(false);

async function proceedToConfirmFromNumber() {
    if (!validatePhone()) return;

    errorMessage.value = "";
    validatingNumber.value = true;

    try {
        const params = new URLSearchParams({
            mobile_number: cleanedPhone.value,
            provider_code: selectedProvider.value.provider_code,
            gateway: selectedProductSource.value === "valuetopup" ? "valuetopup" : "ding",
        });
        const res = await fetch(
            `/retailer/recharge/validate-number?${params.toString()}`,
        );
        const data = await res.json();

        if (!data.success) {
            errorMessage.value = data.error || "Number validation failed.";
            return;
        }

        // Use the canonical number the gateway verified
        if (data.account_number) cleanedPhone.value = data.account_number;
    } catch (e) {
        errorMessage.value = "Network error. Please try again.";
        return;
    } finally {
        validatingNumber.value = false;
    }

    if (props.availableBalance < form.send_value) {
        errorMessage.value =
            "Insufficient wallet balance. Please top up first.";
        return;
    }

    currentStep.value = 5;
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
        if (selectedProductSource.value === "valuetopup") {
            // Valuetopup free-range estimate
            const body = {
                sku_id: valuetopupSkuId.value,
                amount: freeRangeAmount.value,
                currency: form.send_currency,
            };
            const res = await fetch(
                "/retailer/recharge/estimate-cost",
                {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(body),
                },
            );
            const data = await res.json();
            if (data.success && data.pricing) {
                freeRangePricing.value = data.pricing;
                form.send_value = data.pricing.send_value;
                form.receive_value = data.pricing.receive_value;
                form.receive_value_excluding_tax =
                    data.pricing.sales_tax || 0;
            } else {
                freeRangePricing.value = null;
            }
        } else {
            // DingConnect free-range estimate
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
        }
    } catch (e) {
        freeRangePricing.value = null;
    } finally {
        freeRangeLoading.value = false;
    }
}

// =========================================================================
// SUBMIT / REVIEW
// =========================================================================

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
    // Top-up products need a number
    if (!isPinProduct.value && !cleanedPhone.value) {
        errorMessage.value = "Please enter a phone number";
        return false;
    }
    return true;
}

function submitRecharge(action = "buy") {
    if (action === "review") {
        if (!validateBeforeSubmit()) return;
        reviewTimestamp.value = new Date().toLocaleString();
        reviewReference.value = "RCPT-" + Date.now();
        showReviewModal.value = true;
        return;
    }

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
        form.operator_id = selectedProvider.value.provider_id;
        form.provider_code = selectedProvider.value.provider_code;
    }

    // Ensure gateway and VT SKU are set for VT products
    if (selectedProductSource.value === "valuetopup") {
        form.gateway = "valuetopup";
        form.valuetopup_sku_id = valuetopupSkuId.value;
    } else {
        form.gateway = "ding";
        form.valuetopup_sku_id = null;
    }

    form.post("/retailer/recharge", {
        onSuccess: () => {
            showReviewModal.value = false;
            if (isPinProduct.value) {
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
    countryIso.value = "";
    providers.value = [];
    selectedProvider.value = null;
    products.value = [];
    selectedProduct.value = null;
    selectedSkuCode.value = "";
    selectedProductSource.value = "ding";
    valuetopupSkuId.value = null;
    isFreeRangeFlow.value = false;
    freeRangeAmount.value = 0;
    freeRangePricing.value = null;
    promotions.value = [];
    mobileNumber.value = "";
    cleanedPhone.value = "";
    productDescription.value = "";
    productReadmore.value = "";
    errorMessage.value = "";
    phoneError.value = "";
    form.reset();
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
            <h1 class="text-3xl font-bold text-ink-900 mb-1">New Recharge</h1>
            <p class="text-ink-500">
                Instant mobile top-up powered by MK Network
            </p>
        </div>

        <!-- Wallet Balance Alert -->
        <div
            v-if="availableBalance < 10"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 mb-6 flex items-start"
        >
            <p class="text-sm text-red-600">
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
                                    ? 'bg-green-500 text-ink-100'
                                    : currentStep === idx + 1
                                      ? 'bg-primary text-ink-100'
                                      : 'bg-surface-3 text-ink-500 border border-surface-3',
                            ]"
                        >
                            <span v-if="currentStep > idx + 1">&#10003;</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                        <div
                            :class="[
                                'text-[10px] mt-1 font-medium whitespace-nowrap',
                                currentStep >= idx + 1
                                    ? 'text-ink-900'
                                    : 'text-ink-500',
                            ]"
                        >
                            {{ step }}
                        </div>
                    </div>
                    <div
                        v-if="idx < stepNames.length - 1"
                        class="flex-1 h-0.5 mx-1 mb-4 transition min-w-[20px]"
                        :class="
                            currentStep > idx + 1
                                ? 'bg-green-500'
                                : 'bg-surface-3'
                        "
                    ></div>
                </template>
            </div>
        </div>

        <!-- Error -->
        <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6 text-red-600 text-sm"
        >
            {{ errorMessage }}
        </div>

        <!-- =================================================================
             STEP 1: COUNTRY
             ================================================================= -->
        <div v-if="currentStep === 1">
            <h2 class="text-xl font-semibold text-ink-900 mb-4">
                Select Country
            </h2>
            <p class="text-sm text-ink-500 mb-4">
                Choose the destination country for this recharge.
            </p>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <button
                    v-for="country in countries"
                    :key="country.id"
                    @click="selectCountry(country)"
                    class="bg-surface-2 hover:bg-surface-2 border border-surface-3 hover:border-primary rounded-2xl p-5 text-left transition"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-3xl text-primary">{{
                            country.flag_emoji || "🌍"
                        }}</span>
                        <div>
                            <div class="font-semibold text-ink-900">
                                {{ country.name }}
                            </div>
                            <div class="text-xs text-ink-500">
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
             STEP 2: PROVIDER SELECTION
             ================================================================= -->
        <div v-if="currentStep === 2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-ink-900">
                    Select Provider
                </h2>
                <button
                    @click="backToStep(1)"
                    class="text-sm text-ink-500 hover:text-ink-900"
                >
                    &larr; Change country
                </button>
            </div>

            <!-- Provider skeletons while loading -->
            <div
                v-if="loadingProviders"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
            >
                <div
                    v-for="n in 6"
                    :key="n"
                    class="rounded-2xl p-5 border border-surface-3 bg-surface-2/80"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-surface-3 rounded-lg animate-pulse shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-surface-3 rounded w-3/4 animate-pulse"></div>
                            <div class="h-3 bg-surface-3 rounded w-1/2 animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="providers.length === 0" class="text-center py-16">
                <p class="text-ink-500 text-lg mb-2">
                    No operators found for this country.
                </p>
                <button
                    @click="backToStep(1)"
                    class="btn-primary text-ink-900 px-6 py-2 rounded-xl text-sm"
                >
                    &larr; Change country
                </button>
            </div>

            <div
                v-else
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
            >
                <button
                    v-for="provider in providers"
                    :key="provider.provider_code"
                    @click="selectProvider(provider)"
                    :class="[
                        'rounded-2xl p-5 text-left transition relative',
                        selectedProvider?.provider_code ===
                        provider.provider_code
                            ? (provider.source === 'valuetopup_only'
                                ? 'bg-yellow-500/20 border-2 border-yellow-500'
                                : 'bg-primary/20 border-2 border-primary')
                            : (provider.source === 'valuetopup_only'
                                ? 'bg-surface-2 border border-yellow-500/50'
                                : 'bg-surface-2 border border-surface-3 hover:border-primary/50'),
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 bg-white rounded-lg flex items-center justify-center overflow-hidden shrink-0"
                        >
                            <img
                                v-if="provider.logo_url"
                                :src="provider.logo_url"
                                :alt="provider.name"
                                class="w-full h-full object-contain p-1"
                                @error="$event.target.style.display = 'none'"
                            />
                            <span v-else class="text-2xl">📱</span>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-ink-900">
                                {{ provider.name }}
                            </div>
                            <div class="text-xs text-ink-500 mt-1">
                                {{ provider.provider_code }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="
                            selectedProvider?.provider_code ===
                            provider.provider_code
                        "
                        class="text-xs mt-2 font-semibold"
                        :class="provider.source === 'valuetopup_only'
                            ? 'text-yellow-600'
                            : 'text-primary'"
                    >
                        &#10003; Selected
                    </div>
                </button>
            </div>

            <button
                v-if="providers.length > 0"
                @click="proceedToProducts"
                :disabled="!selectedProvider"
                class="mt-6 w-full btn-primary text-ink-100 py-3 rounded-xl font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
            >
                Continue &rarr;
            </button>
        </div>

        <!-- =================================================================
             STEP 3: PRODUCTS (categorized)
             ================================================================= -->
        <div v-if="currentStep === 3">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-ink-900">Select Product</h2>
                <button
                    @click="backToStep(2)"
                    class="text-sm text-ink-500 hover:text-ink-900"
                >
                    &larr; Change provider
                </button>
            </div>

            <!-- Selected provider info -->
            <div
                v-if="selectedProvider"
                class="flex items-center gap-3 mb-4 bg-surface-2 rounded-xl p-3 border border-surface-3"
            >
                <div
                    class="w-8 h-8 bg-white rounded-lg flex items-center justify-center overflow-hidden"
                >
                    <img
                        v-if="selectedProvider.logo_url"
                        :src="selectedProvider.logo_url"
                        class="w-full h-full object-contain p-0.5"
                        @error="$event.target.style.display = 'none'"
                    />
                    <span v-else>📱</span>
                </div>
                <div>
                    <div class="text-sm font-medium text-ink-900">
                        {{ selectedProvider.name }}
                    </div>
                    <div class="text-xs text-ink-500">
                        {{ selectedCountry?.name }}
                    </div>
                </div>
            </div>

            <div v-if="showPromotionBadge" class="mb-4">
                <button
                    @click="showPromotionsModal = true"
                    class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl px-4 py-2 text-yellow-600 text-sm hover:bg-yellow-500/20"
                >
                    🎁 {{ promotions.length }} promotion{{
                        promotions.length > 1 ? "s" : ""
                    }}
                    available
                </button>
            </div>

            <!-- Loading skeletons -->
            <div v-if="loadingProducts" class="space-y-8">
                <!-- Category skeleton: tabs -->
                <div class="flex gap-2 mb-6">
                    <div v-for="n in 4" :key="n" class="h-9 bg-surface-3/80 rounded-xl w-20 animate-pulse"></div>
                </div>
                <!-- Category skeleton: products -->
                <div v-for="n in 2" :key="n" class="mb-8">
                    <div class="h-7 bg-surface-3/80 rounded-lg w-32 mb-4 animate-pulse"></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="m in 6" :key="m" class="rounded-2xl p-5 border border-surface-3/60 bg-surface-2/60">
                            <div class="h-5 bg-surface-3 rounded w-3/4 mb-3 animate-pulse"></div>
                            <div class="flex gap-1.5 mb-3">
                                <div class="h-5 bg-surface-3 rounded-full w-14 animate-pulse"></div>
                                <div class="h-5 bg-surface-3 rounded-full w-10 animate-pulse"></div>
                            </div>
                            <div class="h-8 bg-surface-3 rounded-lg w-24 animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>

            <ProductCategories
                v-else
                :products="allProducts"
                :loading="loadingProducts"
                @select-product="selectProduct"
            />

            <div
                v-if="!loadingProducts && allProducts.length === 0"
                class="text-center py-12"
            >
                <p class="text-ink-500">
                    No products available for this operator.
                </p>
            </div>
        </div>

        <!-- =================================================================
             STEP 4: MOBILE NUMBER (only for Immediate/Top-up products)
             ================================================================= -->
        <div v-if="currentStep === 4 && !isPinProduct">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-ink-900">
                    Enter Mobile Number
                </h2>
                <button
                    @click="backToStep(3)"
                    class="text-sm text-ink-500 hover:text-ink-900"
                >
                    &larr; Change product
                </button>
            </div>

            <!-- Selected product summary -->
            <div
                class="bg-surface-2 border border-surface-3 rounded-2xl p-5 mb-5"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div
                        class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden"
                    >
                        <img
                            v-if="selectedProvider?.logo_url"
                            :src="selectedProvider.logo_url"
                            class="w-full h-full object-contain p-1"
                            @error="$event.target.style.display = 'none'"
                        />
                        <span v-else>📱</span>
                    </div>
                    <div>
                        <div class="font-semibold text-ink-900">
                            {{
                                selectedProduct?.display_text || selectedSkuCode
                            }}
                        </div>
                        <div class="text-xs text-ink-500">
                            {{ selectedProvider?.name }} ·
                            {{ selectedProduct?.redemption_type }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-ink-500">You Pay</span>
                    <span class="text-ink-900 font-bold"
                        >{{ selectedProduct?.send_currency }}
                        {{
                            (selectedProduct?.send_value || 0).toFixed(2)
                        }}</span
                    >
                </div>
            </div>

            <!-- How to redeem (from description API) -->
            <div
                v-if="productReadmore || productDescription"
                class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-5"
            >
                <div class="text-xs text-blue-300 font-medium mb-1">
                    How to Redeem
                </div>
                <div
                    class="text-sm text-blue-200"
                    v-html="productReadmore || productDescription"
                ></div>
            </div>

            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-6 relative">
                <div v-if="validatingNumber" class="absolute inset-0 bg-surface-0/60 rounded-2xl flex items-center justify-center z-10">
                    <div class="inline-block w-6 h-6 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>
                <label class="text-sm text-ink-500 mb-2 block">
                    Mobile Number ({{ selectedCountry?.name }})
                </label>
                <div
                    class="flex items-center bg-surface-3 border border-surface-3 rounded-xl overflow-hidden"
                    :class="{ 'opacity-50': validatingNumber }"
                >
                    <span
                        class="px-4 py-3 text-ink-900 font-semibold border-r border-surface-3"
                    >
                        +{{ selectedCountry?.calling_code }}
                    </span>
                    <input
                        v-model="mobileNumber"
                        type="tel"
                        placeholder="Enter phone number"
                        class="flex-1 bg-transparent px-4 py-3 text-ink-900 outline-none"
                        @input="validatePhone"
                        :disabled="validatingNumber"
                    />
                </div>
                <p v-if="phoneError" class="text-xs text-red-600 mt-2">
                    {{ phoneError }}
                </p>

                <button
                    @click="proceedToConfirmFromNumber"
                    :disabled="!canProceedFromPhone || validatingNumber"
                    class="mt-6 w-full btn-primary text-ink-100 py-3 rounded-xl font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    {{
                        validatingNumber
                            ? "Verifying number..."
                            : "Continue to Review →"
                    }}
                </button>
            </div>
        </div>

        <!-- =================================================================
             STEP 5: CONFIRM / REVIEW
             ================================================================= -->
        <div v-if="currentStep === 5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-ink-900">
                    {{ isPinProduct ? "Confirm PIN Purchase" : "Review Order" }}
                </h2>
                <button
                    @click="backToStep(isPinProduct ? 3 : 4)"
                    class="text-sm text-ink-500 hover:text-ink-900"
                >
                    &larr; Back
                </button>
            </div>

            <div class="bg-surface-2 border border-surface-3 rounded-2xl p-6">
                <!-- Product info row: logo + name + values -->
                <div
                    class="flex items-center gap-4 mb-5 pb-5 border-b border-surface-3"
                >
                    <div
                        class="w-12 h-12 bg-white rounded-lg flex items-center justify-center overflow-hidden shrink-0"
                    >
                        <img
                            v-if="selectedProvider?.logo_url"
                            :src="selectedProvider.logo_url"
                            class="w-full h-full object-contain p-1"
                            @error="$event.target.style.display = 'none'"
                        />
                        <span v-else class="text-2xl">📱</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-ink-900 truncate">
                            {{
                                selectedProduct?.display_text || selectedSkuCode
                            }}
                        </div>
                        <div class="text-xs text-ink-500">
                            {{ selectedProvider?.name }} ·
                            {{ selectedProduct?.redemption_type }}
                        </div>
                    </div>
                </div>

                <!-- Three values: You Paid | PIN Value / Customer Gets | Number/Redemption -->
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <div class="bg-surface-3 rounded-xl p-3 text-center">
                        <div
                            class="text-[10px] text-ink-500 uppercase tracking-wider mb-1"
                        >
                            You Pay
                        </div>
                        <div class="text-sm font-bold text-ink-900">
                            {{ selectedProduct?.send_currency || "GBP" }}
                            {{ (selectedProduct?.send_value || 0).toFixed(2) }}
                        </div>
                    </div>
                    <div
                        class="bg-yellow-500/10 rounded-xl p-3 text-center border border-yellow-500/20"
                    >
                        <div
                            class="text-[10px] text-yellow-600 uppercase tracking-wider mb-1"
                        >
                            {{ isPinProduct ? "PIN Value" : "Customer Gets" }}
                        </div>
                        <div class="text-sm font-bold text-yellow-600">
                            {{ selectedProduct?.receive_currency || "GBP" }}
                            {{
                                (selectedProduct?.receive_value || 0).toFixed(2)
                            }}
                        </div>
                    </div>
                    <div class="bg-surface-3 rounded-xl p-3 text-center">
                        <div
                            class="text-[10px] text-ink-500 uppercase tracking-wider mb-1"
                        >
                            {{ isPinProduct ? "Redemption" : "Number" }}
                        </div>
                        <div class="text-xs text-ink-900 font-medium">
                            {{
                                isPinProduct
                                    ? "Any SIM can redeem"
                                    : "+ " + (cleanedPhone || "—")
                            }}
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="space-y-2 mb-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-500">SKU</span>
                        <span class="text-ink-900 font-mono text-xs">{{
                            selectedSkuCode
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-500">Redemption</span>
                        <span class="text-ink-900">{{
                            selectedProduct?.redemption_type
                        }}</span>
                    </div>
                    <div
                        v-if="selectedProduct?.validity_period"
                        class="flex justify-between"
                    >
                        <span class="text-ink-500">Validity</span>
                        <span class="text-green-400">{{
                            parseValidityPeriod(selectedProduct.validity_period)
                        }}</span>
                    </div>
                    <div
                        v-if="selectedProduct?.benefits?.length"
                        class="flex justify-between"
                    >
                        <span class="text-ink-500">Benefits</span>
                        <span class="text-ink-900">{{
                            selectedProduct.benefits.join(", ")
                        }}</span>
                    </div>
                </div>

                <!-- Description / How to Redeem -->
                <div v-if="productReadmore || productDescription" class="mb-5">
                    <div
                        class="text-[10px] text-ink-500 uppercase tracking-wider mb-2"
                    >
                        {{
                            isPinProduct
                                ? "How to Redeem"
                                : "Product Information"
                        }}
                    </div>
                    <div
                        v-if="productReadmore"
                        class="bg-surface-3 rounded-xl p-4 mb-2 text-sm text-ink-700"
                        v-html="productReadmore"
                    ></div>
                    <div
                        v-if="productDescription"
                        class="bg-surface-3 rounded-xl p-4 text-sm text-ink-700"
                        v-html="productDescription"
                    ></div>
                </div>

                <!-- Free range input (if applicable) -->
                <div v-if="isFreeRangeFlow" class="mb-5">
                    <label class="text-sm text-ink-500 mb-2 block"
                        >Enter Amount ({{
                            selectedProduct?.send_currency
                        }})</label
                    >
                    <input
                        v-model.number="freeRangeAmount"
                        @input="fetchFreeRangePricing"
                        type="number"
                        :min="selectedProduct?.min_send_value"
                        :max="selectedProduct?.max_send_value"
                        class="w-full bg-surface-3 border border-surface-3 rounded-xl px-4 py-3 text-ink-900 text-lg outline-none"
                    />
                    <p class="text-xs text-ink-500 mt-2">
                        Min: {{ selectedProduct?.send_currency }}
                        {{ selectedProduct?.min_send_value }} &middot; Max:
                        {{ selectedProduct?.send_currency }}
                        {{ selectedProduct?.max_send_value }}
                    </p>
                    <div v-if="freeRangeLoading" class="mt-4 text-center">
                        <div
                            class="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"
                        ></div>
                        <span class="ml-2 text-ink-500">Calculating...</span>
                    </div>
                    <div
                        v-else-if="freeRangePricing"
                        class="mt-4 bg-primary/10 border border-primary/30 rounded-xl p-4"
                    >
                        <div class="text-sm text-ink-500 mb-1">
                            Customer will receive:
                        </div>
                        <div class="text-2xl font-bold text-primary-light">
                            {{ freeRangePricing.receive_currency }}
                            {{ freeRangePricing.receive_value.toFixed(2) }}
                        </div>
                        <div
                            v-if="
                                selectedProductSource === 'valuetopup' &&
                                freeRangePricing.face_value
                            "
                            class="text-sm text-yellow-600 mt-1"
                        >
                            Face value: {{ freeRangePricing.face_value_currency || 'GBP' }}
                            {{ freeRangePricing.face_value.toFixed(2) }}
                        </div>
                    </div>
                </div>

                <!-- Action -->
                <div class="flex gap-3">
                    <button
                        @click="submitRecharge('review')"
                        :disabled="
                            submitting || (isFreeRangeFlow && !freeRangePricing)
                        "
                        class="flex-1 border border-surface-3 text-ink-900 py-3 rounded-xl font-semibold hover:bg-surface-2 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        Review Order
                    </button>
                    <button
                        @click="submitRecharge('buy')"
                        :disabled="
                            submitting || (isFreeRangeFlow && !freeRangePricing)
                        "
                        class="flex-1 btn-primary text-ink-100 py-3 rounded-xl font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{
                            submitting
                                ? "Processing..."
                                : isPinProduct
                                  ? "Purchase PIN"
                                  : "Recharge Now"
                        }}
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
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto"
        >
            <div
                class="bg-surface-2 border border-surface-3 rounded-2xl max-w-md w-full max-h-[90vh] flex flex-col"
            >
                <div class="flex items-center justify-between mb-4 shrink-0">
                    <h3 class="text-lg font-semibold text-ink-900">
                        🎁 Available Promotions
                    </h3>
                    <button
                        @click="closePromotionsModal"
                        class="text-ink-500 hover:text-ink-900"
                    >
                        &times;
                    </button>
                </div>
                <div class="space-y-3 overflow-y-auto flex-1 pr-1">
                    <div
                        v-for="promo in promotions"
                        :key="promo.localization_key"
                        class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4"
                    >
                        <div class="font-semibold text-yellow-600 mb-1">
                            {{ promo.promotion_name }}
                        </div>
                        <div class="text-sm text-yellow-200">
                            {{ promo.display_text }}
                        </div>
                        <div
                            v-if="promo.from_date || promo.to_date"
                            class="text-xs text-yellow-600 mt-2"
                        >
                            {{ promo.from_date }}
                            {{ promo.from_date && promo.to_date ? "to" : "" }}
                            {{ promo.to_date }}
                        </div>
                    </div>
                </div>
                <button
                    @click="closePromotionsModal"
                    class="mt-6 w-full btn-primary text-ink-900 py-3 rounded-xl font-semibold shrink-0"
                >
                    Got it
                </button>
            </div>
        </div>

        <!-- =================================================================
             REVIEW MODAL
             ================================================================= -->
        <div
            v-if="showReviewModal"
            @click.self="cancelReview"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto"
        >
            <div
                class="bg-surface-2 border border-surface-3 rounded-2xl max-w-md w-full p-6 max-h-[90vh] flex flex-col"
            >
                <div
                    class="text-center border-b border-dashed border-surface-3 pb-4 mb-4 shrink-0"
                >
                    <h3 class="text-lg font-bold text-ink-900">Review Order</h3>
                    <p class="text-xs text-ink-500 font-mono mt-1">
                        {{ reviewReference }}
                    </p>
                    <p class="text-xs text-ink-500">{{ reviewTimestamp }}</p>
                </div>

                <div class="overflow-y-auto flex-1 pr-1">
                    <div class="space-y-2 text-sm mb-4">
                        <div class="flex justify-between">
                            <span class="text-ink-500">Country</span>
                            <span class="text-ink-900"
                                >{{ selectedCountry?.name }} ({{
                                    currentCountryIso
                                }})</span
                            >
                        </div>
                        <div v-if="!isPinProduct" class="flex justify-between">
                            <span class="text-ink-500">Phone</span>
                            <span class="text-ink-900">+{{ cleanedPhone }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-500">Operator</span>
                            <span class="text-ink-900">{{
                                selectedProvider?.name
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-500">Product</span>
                            <span class="text-ink-900">{{
                                selectedProduct?.display_text || selectedSkuCode
                            }}</span>
                        </div>
                        <div
                            v-if="selectedProduct?.validity_period"
                            class="flex justify-between"
                        >
                            <span class="text-ink-500">Validity</span>
                            <span class="text-green-400">{{
                                parseValidityPeriod(
                                    selectedProduct.validity_period,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="border-t border-dashed border-surface-3 pt-3 mb-4"
                    >
                        <div class="flex justify-between items-baseline">
                            <span class="text-ink-500">You Pay</span>
                            <span class="text-xl font-bold text-ink-900">
                                {{ form.send_currency }}
                                {{ form.send_value.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-ink-500">Customer Gets</span>
                            <span class="text-green-400">
                                {{ form.receive_currency }}
                                {{ form.receive_value.toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="productReadmore || productDescription"
                        class="mb-4 text-xs text-ink-500"
                    >
                        <div
                            v-html="productReadmore || productDescription"
                        ></div>
                    </div>
                    <div class="flex gap-3 mt-4 shrink-0">
                        <button
                            @click="cancelReview"
                            :disabled="submitting"
                            class="flex-1 border border-surface-3 text-ink-900 py-3 rounded-xl font-semibold hover:bg-surface-2 disabled:opacity-60"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitRecharge('buy')"
                            :disabled="submitting"
                            class="flex-1 btn-primary text-ink-100 py-3 rounded-xl font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {{
                                submitting
                                    ? "Processing..."
                                    : isPinProduct
                                      ? "Purchase PIN"
                                      : "Proceed with Recharge"
                            }}
                        </button>
                    </div>
                </div>

                <!-- =================================================================
             PIN / VOUCHER MODAL
             ================================================================= -->
                <div
                    v-if="showPinModal"
                    @click.self="closePinModal"
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto"
                >
                    <div
                        class="bg-surface-2 border border-green-500/30 rounded-2xl max-w-md w-full p-6 max-h-[90vh] flex flex-col"
                    >
                        <div class="text-center shrink-0">
                            <div
                                class="w-12 h-12 mx-auto bg-green-500/20 rounded-full flex items-center justify-center mb-4"
                            >
                                &#10003;
                            </div>
                            <h3 class="text-xl font-bold text-ink-900 mb-2">
                                Recharge Successful!
                            </h3>
                            <p class="text-sm text-ink-500 mb-4">
                                Please share this PIN with your customer
                            </p>
                        </div>
                        <div class="overflow-y-auto flex-1 pr-1">
                            <div class="bg-surface-3 rounded-xl p-4 mb-4">
                                <div class="text-xs text-ink-500 mb-1">
                                    Receipt Number
                                </div>
                                <div class="text-ink-900 font-mono text-sm mb-3">
                                    {{ receiptNumber }}
                                </div>
                                <div class="text-xs text-ink-500 mb-1">
                                    PIN / Voucher Code
                                </div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex-1 bg-surface-0 rounded-lg p-3 border border-primary/30 text-primary-light font-bold break-all"
                                        v-html="
                                            receiptText.replace(/\n/g, '<br>')
                                        "
                                    ></div>
                                    <button
                                        @click="copyPin"
                                        class="text-xs bg-surface-3 px-3 py-2 rounded text-ink-500 hover:text-ink-900 transition whitespace-nowrap"
                                    >
                                        📋 Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="closePinModal"
                            class="w-full btn-primary text-ink-900 py-3 rounded-xl font-semibold shrink-0 mt-4"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
