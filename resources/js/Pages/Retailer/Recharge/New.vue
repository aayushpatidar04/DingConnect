<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import RetailerLayout from '@/Layouts/RetailerLayout.vue';
import ProductCategories from '@/Components/ProductCategories.vue';
import { ref, onMounted, computed } from 'vue';

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    availableBalance: Number,
    countries: Array,
});

const currentStep = ref(1);
const selectedCountry = ref(null);
const mobileNumber = ref('');
const cleanedPhone = ref('');
const countryIso = ref('');
const phoneError = ref('');
const providers = ref([]);
const selectedProvider = ref(null);
const regions = ref([]);
const selectedRegion = ref(null);
const providerLive = ref(true);
const products = ref([]);
const promotions = ref([]);
const showPromotionsModal = ref(false);
const loadingProducts = ref(false);
const loadingProviders = ref(false);
const selectedProduct = ref(null);
const selectedSkuCode = ref('');
const isFreeRangeFlow = ref(false);
const freeRangeAmount = ref(0);
const freeRangePricing = ref(null);
const freeRangeLoading = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const showPinModal = ref(false);
const receiptText = ref('');
const receiptNumber = ref('');

const form = useForm({
    mobile_number: '',
    country_id: '',
    sku_code: '',
    send_value: 0,
    receive_value: 0,
    send_currency: 'GBP',
    receive_currency: 'GBP',
    display_text: '',
    default_display_text: '',
    validity_period: '',
    description_markdown: '',
    readmore_markdown: '',
    benefits: [],
    redemption_type: 'Immediate',
    product_type: '',
    region_code: '',
    provider_code: '',
    free_range: false,
    receive_value_excluding_tax: 0,
});

const stepNames = ['Country', 'Phone', 'Provider', 'Region', 'Plan', 'Confirm'];
const totalSteps = stepNames.length;

const canProceedFromPhone = computed(() => cleanedPhone.value.length >= 7 && !phoneError.value);

const canProceedFromProvider = computed(() => !!selectedProvider.value);

const canProceedFromRegion = computed(() => true);

const canProceedFromPlan = computed(() => !!selectedSkuCode.value || isFreeRangeFlow.value);

const currentCountryIso = computed(() => {
    if (countryIso.value) return countryIso.value;
    if (selectedCountry.value?.iso_code) return selectedCountry.value.iso_code;
    return 'GB';
});

const showPromotionBadge = computed(() => promotions.value.length > 0);

function parseValidityPeriod(iso) {
    if (!iso || iso.trim() === '') return null;
    const match = iso.match(/P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?/);
    if (!match) return null;
    const parts = [];
    if (match[1]) parts.push(match[1] + 'y');
    if (match[2]) parts.push(match[2] + 'm');
    if (match[3]) parts.push(match[3] + 'w');
    if (match[4]) parts.push(match[4] + 'd');
    return parts.length ? 'Valid for ' + parts.join(' ') : null;
}

function formatValidity(iso) {
    return parseValidityPeriod(iso);
}

function selectCountry(country) {
    selectedCountry.value = country;
    currentStep.value = 2;
    phoneError.value = '';
    errorMessage.value = '';
}

function backToStep(step) {
    currentStep.value = step;
    errorMessage.value = '';
}

function validatePhone() {
    const cleaned = mobileNumber.value.replace(/\D/g, '');
    cleanedPhone.value = cleaned;

    if (cleaned.length < 7) {
        phoneError.value = 'Please enter a valid phone number';
        return false;
    }

    if (selectedCountry.value?.calling_code) {
        const code = selectedCountry.value.calling_code.replace(/\D/g, '');
        if (cleaned.startsWith(code)) {
            phoneError.value = '';
            return true;
        } else {
            const withoutCode = cleaned.startsWith('0') ? cleaned.slice(1) : cleaned;
            cleanedPhone.value = code + withoutCode;
            phoneError.value = '';
            return true;
        }
    }

    phoneError.value = '';
    return true;
}

async function proceedToProviders() {
    if (!validatePhone()) return;
    currentStep.value = 3;
    await loadProviders();
}

async function loadProviders() {
    loadingProviders.value = true;
    providers.value = [];
    selectedProvider.value = null;
    errorMessage.value = '';

    try {
        const res = await fetch(
            `/retailer/recharge/operators?phone_number=${cleanedPhone.value}`
        );
        const data = await res.json();
        if (data.success) {
            countryIso.value = data.country_iso;
            providers.value = data.providers || [];

            if (providers.value.length === 0) {
                errorMessage.value = 'No operators available for this phone number.';
            } else if (providers.value.length === 1) {
                await selectProvider(providers.value[0]);
            }
        } else {
            errorMessage.value = data.error || 'Failed to load operators.';
        }
    } catch (e) {
        errorMessage.value = 'Network error. Please try again.';
    } finally {
        loadingProviders.value = false;
    }
}

async function selectProvider(provider) {
    selectedProvider.value = provider;
    errorMessage.value = '';
    await checkProviderStatus();
    await loadRegions();
    await loadProducts();
}

async function checkProviderStatus() {
    try {
        const res = await fetch(
            `/retailer/recharge/provider-status?provider_code=${selectedProvider.value.provider_code}`
        );
        const data = await res.json();
        providerLive.value = data.is_live !== false;

        if (!providerLive.value) {
            errorMessage.value =
                'Selected provider is down at this moment, please try after some time.';
        }
    } catch (e) {
        providerLive.value = true;
    }
}

async function loadRegions() {
    regions.value = [];
    selectedRegion.value = null;

    try {
        const res = await fetch(
            `/retailer/recharge/regions?provider_code=${selectedProvider.value.provider_code}`
        );
        const data = await res.json();
        if (data.success) {
            regions.value = data.regions || [];
            if (regions.value.length === 1) {
                selectedRegion.value = regions.value[0];
                currentStep.value = 5;
            } else if (regions.value.length > 1) {
                currentStep.value = 4;
            } else {
                currentStep.value = 5;
            }
        }
    } catch (e) {
        currentStep.value = 5;
    }
}

async function selectRegion(region) {
    selectedRegion.value = region;
    currentStep.value = 5;
    await loadProducts();
}

async function loadProducts() {
    loadingProducts.value = true;
    products.value = [];
    selectedProduct.value = null;
    errorMessage.value = '';

    try {
        const params = new URLSearchParams();
        params.append('account_number', cleanedPhone.value);
        if (selectedProvider.value) params.append('provider_code', selectedProvider.value.provider_code);
        if (selectedRegion.value) params.append('region_code', selectedRegion.value.region_code);

        const res = await fetch(`/retailer/recharge/products?${params.toString()}`);
        const data = await res.json();

        if (data.success) {
            products.value = data.products || [];

            // Separate by redemption type
            const readReceiptProducts = products.value.filter(p => p.redemption_type === 'ReadReceipt');
            const immediateProducts = products.value.filter(p => p.redemption_type !== 'ReadReceipt');

            if (immediateProducts.length === 0 && readReceiptProducts.length > 0) {
                // All are PIN/Voucher products - go to PIN flow
                errorMessage.value = 'Only PIN/Voucher products available. Please use PIN flow.';
            }

            // Load promotions
            await loadPromotions();
        } else {
            errorMessage.value = data.error || 'Failed to load products.';
        }
    } catch (e) {
        errorMessage.value = 'Network error. Please try again.';
    } finally {
        loadingProducts.value = false;
    }
}

async function loadPromotions() {
    try {
        const params = new URLSearchParams();
        params.append('country_isos', currentCountryIso.value);
        if (selectedProvider.value) params.append('provider_codes', selectedProvider.value.provider_code);

        const res = await fetch(`/retailer/recharge/promotions?${params.toString()}`);
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
    form.description_markdown = product.description_markdown || '';
    form.readmore_markdown = product.readmore_markdown || '';
    form.benefits = product.benefits || [];
    form.redemption_type = product.redemption_type || 'Immediate';
    form.product_type = product.product_type || '';
    form.region_code = selectedRegion.value?.region_code || '';
    form.provider_code = selectedProvider.value?.provider_code || '';
    form.receive_value_excluding_tax = product.receive_value_excluding_tax || product.receive_value || 0;
    form.free_range = isFreeRangeFlow.value;

    currentStep.value = 6;
}

function backToProducts() {
    selectedProduct.value = null;
    selectedSkuCode.value = '';
    isFreeRangeFlow.value = false;
    form.sku_code = '';
    currentStep.value = 5;
}

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
        params.append('sku_code', selectedProduct.value.sku_code);
        params.append('send_value', freeRangeAmount.value);
        params.append('send_currency_iso', form.send_currency);

        const res = await fetch(`/retailer/recharge/pricing?${params.toString()}`);
        const data = await res.json();

        if (data.success && data.pricing) {
            freeRangePricing.value = data.pricing;
            form.send_value = data.pricing.send_value;
            form.receive_value = data.pricing.receive_value;
            form.receive_value_excluding_tax = data.pricing.receive_value_excluding_tax;
        } else {
            freeRangePricing.value = null;
        }
    } catch (e) {
        freeRangePricing.value = null;
    } finally {
        freeRangeLoading.value = false;
    }
}

function submitRecharge(action = 'buy') {
    errorMessage.value = '';

    if (!canProceedFromPlan.value) {
        errorMessage.value = 'Please select a product';
        return;
    }

    if (isFreeRangeFlow.value && !freeRangePricing.value) {
        errorMessage.value = 'Please enter a valid amount';
        return;
    }

    if (props.availableBalance < form.send_value) {
        errorMessage.value = 'Insufficient wallet balance. Please top up first.';
        return;
    }

    submitting.value = true;
    form.mobile_number = cleanedPhone.value;
    form.country_id = selectedCountry.value.id;

    form.post('/retailer/recharge', {
        onSuccess: () => {
            if (selectedProduct.value.redemption_type === 'ReadReceipt') {
                showPinModal.value = true;
            }
        },
        onError: errors => {
            errorMessage.value = Object.values(errors)[0] || 'Recharge failed';
            submitting.value = false;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function closePinModal() {
    showPinModal.value = false;
    receiptText.value = '';
    receiptNumber.value = '';
}

function resetFlow() {
    currentStep.value = 1;
    selectedCountry.value = null;
    mobileNumber.value = '';
    cleanedPhone.value = '';
    countryIso.value = '';
    providers.value = [];
    selectedProvider.value = null;
    regions.value = [];
    selectedRegion.value = null;
    products.value = [];
    selectedProduct.value = null;
    selectedSkuCode.value = '';
    isFreeRangeFlow.value = false;
    freeRangeAmount.value = 0;
    freeRangePricing.value = null;
    promotions.value = [];
    errorMessage.value = '';
    phoneError.value = '';
}

onMounted(() => {
    if (props.auth?.user && window.Echo?.private) {
        window.Echo.private(`retailer.${props.auth.user.id}`).listen(
            '.RechargeSuccess',
            e => {
                if (e.transaction?.receipt_text) {
                    receiptText.value = e.transaction.receipt_text;
                    receiptNumber.value = e.transaction.receipt_number || '';
                    showPinModal.value = true;
                }
            }
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
            <p class="text-dark-300">Instant mobile top-up powered by DingConnect</p>
        </div>

        <!-- Wallet Balance Alert -->
        <div
            v-if="availableBalance < 10"
            class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 mb-6 flex items-start"
        >
            <p class="text-sm text-red-300">
                Low wallet balance.
                <a href="/retailer/wallet" class="underline font-medium hover:text-red-200">Top up now →</a>
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
                            <span v-if="currentStep > idx + 1">✓</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                        <div
                            :class="[
                                'text-[10px] mt-1 font-medium whitespace-nowrap',
                                currentStep >= idx + 1 ? 'text-white' : 'text-dark-400',
                            ]"
                        >
                            {{ step }}
                        </div>
                    </div>
                    <div
                        v-if="idx < stepNames.length - 1"
                        :class="[
                            'flex-1 h-0.5 mx-1 mb-4 transition min-w-[20px]',
                            currentStep > idx + 1 ? 'bg-green-500' : 'bg-dark-700',
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

        <!-- STEP 1: Country -->
        <div v-if="currentStep === 1">
            <h2 class="text-xl font-semibold text-white mb-4">Select Country</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <button
                    v-for="country in countries"
                    :key="country.id"
                    @click="selectCountry(country)"
                    class="bg-dark-800 hover:bg-dark-700 border border-dark-600 hover:border-primary rounded-2xl p-5 text-left transition"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">{{ country.flag_emoji || '🌍' }}</span>
                        <div>
                            <div class="font-semibold text-white">{{ country.name }}</div>
                            <div class="text-xs text-dark-400">
                                {{ country.iso_code }} · +{{ country.calling_code }}
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- STEP 2: Phone Number -->
        <div v-if="currentStep === 2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Enter Phone Number</h2>
                <button @click="backToStep(1)" class="text-sm text-dark-300 hover:text-white">← Change country</button>
            </div>

            <div class="bg-dark-800 border border-dark-600 rounded-2xl p-6">
                <label class="text-sm text-dark-300 mb-2 block">Mobile Number</label>
                <div class="flex items-center bg-dark-700 border border-dark-600 rounded-xl overflow-hidden">
                    <span class="px-4 py-3 text-white font-semibold border-r border-dark-600">
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
                <p v-if="phoneError" class="text-xs text-red-400 mt-2">{{ phoneError }}</p>
                <p class="text-xs text-dark-400 mt-2">Country code will be added automatically</p>

                <button
                    @click="proceedToProviders"
                    :disabled="!canProceedFromPhone"
                    class="mt-6 w-full btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Continue →
                </button>
            </div>
        </div>

        <!-- STEP 3: Provider Selection -->
        <div v-if="currentStep === 3">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Select Operator</h2>
                <button @click="backToStep(2)" class="text-sm text-dark-300 hover:text-white">← Change phone</button>
            </div>

            <div v-if="loadingProviders" class="text-center py-16">
                <div class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-dark-300">Detecting operator...</p>
            </div>

            <div
                v-else-if="providers.length === 1"
                class="bg-dark-800 border border-primary/30 rounded-2xl p-6 text-center"
            >
                <div class="text-dark-300 text-sm mb-2">Detected operator:</div>
                <div class="text-2xl font-bold text-white">{{ providers[0].name }}</div>
            </div>

            <div v-else-if="providers.length > 1" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <button
                    v-for="provider in providers"
                    :key="provider.provider_code"
                    @click="selectProvider(provider)"
                    class="bg-dark-800 hover:bg-dark-700 border border-dark-600 hover:border-primary rounded-2xl p-5 transition"
                >
                    <div class="text-lg font-semibold text-white">{{ provider.name }}</div>
                    <div class="text-xs text-dark-400 mt-1">{{ provider.provider_code }}</div>
                </button>
            </div>
        </div>

        <!-- STEP 4: Region Selection (only if multiple regions) -->
        <div v-if="currentStep === 4 && regions.length > 0">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Select Region</h2>
                <button @click="backToStep(3)" class="text-sm text-dark-300 hover:text-white">← Back</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <button
                    v-for="region in regions"
                    :key="region.region_code"
                    @click="selectRegion(region)"
                    class="bg-dark-800 hover:bg-dark-700 border border-dark-600 hover:border-primary rounded-2xl p-5 transition"
                >
                    <div class="text-lg font-semibold text-white">{{ region.name }}</div>
                    <div class="text-xs text-dark-400 mt-1">{{ region.region_code }}</div>
                </button>
            </div>
        </div>

        <!-- STEP 5: Products (categorized) -->
        <div v-if="currentStep === 5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Select Product</h2>
                <button
                    @click="regions.length > 1 ? backToStep(4) : backToStep(3)"
                    class="text-sm text-dark-300 hover:text-white"
                >
                    ← Back
                </button>
            </div>

            <!-- Promotions Badge -->
            <div v-if="showPromotionBadge" class="mb-4">
                <button
                    @click="showPromotionsModal = true"
                    class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl px-4 py-2 text-yellow-300 text-sm hover:bg-yellow-500/20"
                >
                    🎁 {{ promotions.length }} promotion{{ promotions.length > 1 ? 's' : '' }} available
                </button>
            </div>

            <ProductCategories
                :products="products"
                :loading="loadingProducts"
                @select-product="selectProduct"
            />
        </div>

        <!-- STEP 6: Confirm / Free Range -->
        <div v-if="currentStep === 6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">{{ isFreeRangeFlow ? 'Enter Amount' : 'Review Order' }}</h2>
                <button @click="backToProducts" class="text-sm text-dark-300 hover:text-white">← Back to products</button>
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
                        Min: {{ selectedProduct?.send_currency }} {{ selectedProduct?.min_send_value }} ·
                        Max: {{ selectedProduct?.send_currency }} {{ selectedProduct?.max_send_value }}
                    </p>

                    <div v-if="freeRangeLoading" class="mt-4 text-center">
                        <div class="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <span class="ml-2 text-dark-300">Calculating...</span>
                    </div>

                    <div v-else-if="freeRangePricing" class="mt-4 bg-primary/10 border border-primary/30 rounded-xl p-4">
                        <div class="text-sm text-dark-300 mb-1">Customer will receive:</div>
                        <div class="text-2xl font-bold text-primary-light">
                            {{ freeRangePricing.receive_currency }} {{ freeRangePricing.receive_value.toFixed(2) }}
                        </div>
                        <div
                            v-if="freeRangePricing.receive_value !== freeRangePricing.receive_value_excluding_tax"
                            class="text-xs text-dark-400 mt-2"
                        >
                            (excluding tax: {{ freeRangePricing.receive_currency }}
                            {{ freeRangePricing.receive_value_excluding_tax.toFixed(2) }})
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Phone</span>
                        <span class="text-white">+{{ cleanedPhone }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Provider</span>
                        <span class="text-white">{{ selectedProvider?.name }}</span>
                    </div>
                    <div v-if="selectedRegion" class="flex justify-between text-sm">
                        <span class="text-dark-300">Region</span>
                        <span class="text-white">{{ selectedRegion.name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-dark-300">Product</span>
                        <span class="text-white">{{ selectedProduct?.display_text || selectedSkuCode }}</span>
                    </div>
                    <div v-if="selectedProduct?.validity_period" class="flex justify-between text-sm">
                        <span class="text-dark-300">Validity</span>
                        <span class="text-green-400">{{ formatValidity(selectedProduct.validity_period) }}</span>
                    </div>
                    <div class="border-t border-dark-600 pt-3 mt-3">
                        <div class="flex justify-between items-baseline">
                            <span class="text-dark-300">You Pay</span>
                            <span class="text-2xl font-bold text-white">
                                {{ form.send_currency }} {{ form.send_value.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-dark-300">Customer Gets</span>
                            <span class="text-green-400">
                                {{ form.receive_currency }} {{ form.receive_value.toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="selectedProduct?.description_markdown" class="mb-4 text-sm text-dark-300 prose prose-invert max-w-none">
                    <div v-html="selectedProduct.description_markdown"></div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="submitRecharge('review')"
                        :disabled="submitting || (isFreeRangeFlow && !freeRangePricing)"
                        class="flex-1 border border-dark-600 text-white py-3 rounded-xl font-semibold hover:bg-dark-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Review Order
                    </button>
                    <button
                        @click="submitRecharge('buy')"
                        :disabled="submitting || (isFreeRangeFlow && !freeRangePricing)"
                        class="flex-1 btn-primary text-white py-3 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ submitting ? 'Processing...' : 'Buy Now' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Promotions Modal -->
        <div
            v-if="showPromotionsModal"
            @click.self="closePromotionsModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
            <div class="bg-dark-800 border border-dark-600 rounded-2xl max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">🎁 Available Promotions</h3>
                    <button @click="closePromotionsModal" class="text-dark-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <div
                        v-for="promo in promotions"
                        :key="promo.localization_key"
                        class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4"
                    >
                        <div class="font-semibold text-yellow-300 mb-1">{{ promo.promotion_name }}</div>
                        <div class="text-sm text-yellow-200">{{ promo.display_text }}</div>
                        <div v-if="promo.from_date || promo.to_date" class="text-xs text-yellow-400 mt-2">
                            {{ promo.from_date }} {{ promo.from_date && promo.to_date ? 'to' : '' }} {{ promo.to_date }}
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

        <!-- PIN/Voucher Modal -->
        <div
            v-if="showPinModal"
            @click.self="closePinModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
            <div class="bg-dark-800 border border-green-500/30 rounded-2xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto bg-green-500/20 rounded-full flex items-center justify-center mb-4">
                        ✓
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Recharge Successful!</h3>
                    <p class="text-sm text-dark-300 mb-4">Please share this PIN with your customer</p>
                </div>
                <div class="bg-dark-700 rounded-xl p-4 mb-4">
                    <div class="text-xs text-dark-400 mb-1">Receipt Number</div>
                    <div class="text-white font-mono text-sm mb-3">{{ receiptNumber }}</div>
                    <div class="text-xs text-dark-400 mb-1">PIN / Voucher Code</div>
                    <div class="bg-dark-900 rounded-lg p-3 border border-primary/30 text-primary-light font-bold text-center break-all">
                        {{ receiptText }}
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
