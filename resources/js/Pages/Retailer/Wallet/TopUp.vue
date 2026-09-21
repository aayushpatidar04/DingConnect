<script setup>
import { Head, router } from '@inertiajs/vue3';
import RetailerLayout from '@/Layouts/RetailerLayout.vue';
import { ref, onMounted, nextTick } from 'vue';

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    wallet: Object,
    availableBalance: Number,
    stripePublishableKey: String,
});

const selectedAmount = ref(null);
const customAmount = ref('');
const processing = ref(false);
const errorMessage = ref('');
const cardElement = ref(null);
const stripeLoaded = ref(false);
const stripe = ref(null);
const elements = ref(null);

const presetAmounts = [50, 100, 200, 500, 1000, 2000];

function selectAmount(amount) {
    selectedAmount.value = amount;
    customAmount.value = '';
    errorMessage.value = '';
}

function getAmount() {
    return parseFloat(customAmount.value || selectedAmount.value || 0);
}

async function loadStripe() {
    if (!props.stripePublishableKey) {
        stripeLoaded.value = false;
        errorMessage.value = 'Payment credentials not configured. Please contact support.';
        return;
    }

    const init = () => {
        stripeLoaded.value = true;
        stripe.value = window.Stripe(props.stripePublishableKey);
        nextTick(() => {
            initCardElement();
        });
    };

    if (window.Stripe) {
        init();
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://js.stripe.com/v3/';
    script.onload = init;
    script.onerror = () => {
        errorMessage.value = 'Failed to load Stripe. Please refresh.';
    };
    document.head.appendChild(script);
}

function initCardElement() {
    if (!stripe.value) {
        errorMessage.value = 'Stripe is not initialised. Please refresh.';
        return;
    }

    try {
        elements.value = stripe.value.elements({
            appearance: { theme: 'none' },
        });

        if (cardElement.value) {
            const card = elements.value.create('card', {
                style: {
                    base: {
                        color: '#fff',
                        fontFamily: 'ui-sans-serif, system-ui, sans-serif',
                        fontSize: '16px',
                        '::placeholder': { color: '#6b7280' },
                    },
                    invalid: { color: '#ef4444' },
                },
            });
            card.mount(cardElement.value);
        }
    } catch (e) {
        console.error('Stripe init error:', e);
        errorMessage.value = 'Failed to initialise payment form. Please refresh.';
    }
}

async function initiateTopUp() {
    const amount = getAmount();

    if (!amount || amount < 10) {
        errorMessage.value = 'Please select or enter a minimum amount of £10';
        return;
    }

    processing.value = true;
    errorMessage.value = '';

    try {
        const res = await fetch('/retailer/wallet/topup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ amount }),
        });

        const data = await res.json();

        if (!data.success) {
            errorMessage.value = data.error || 'Failed to initiate top-up';
            processing.value = false;
            return;
        }

        await confirmCardPayment(data.client_secret);
    } catch (e) {
        errorMessage.value = 'Network error. Please try again.';
        processing.value = false;
    }
}

async function confirmCardPayment(clientSecret) {
    if (!stripe.value || !elements.value) {
        errorMessage.value = 'Stripe not loaded yet. Please wait.';
        processing.value = false;
        return;
    }

    const card = elements.value.getElement('card');

    if (!card) {
        errorMessage.value = 'Card form failed to initialise. Please check Stripe credentials are configured and refresh the page.';
        processing.value = false;
        return;
    }

    const { error, paymentIntent } = await stripe.value.confirmCardPayment(clientSecret, {
        payment_method: { card: card },
    });

    if (error) {
        errorMessage.value = error.message || 'Payment failed. Please try again.';
        processing.value = false;
    } else if (paymentIntent.status === 'succeeded') {
        // Webhook will handle wallet credit, redirect to wallet
        setTimeout(() => {
            router.visit('/retailer/wallet');
        }, 1500);
    } else {
        errorMessage.value = 'Payment status: ' + paymentIntent.status + '. Please wait for confirmation.';
        processing.value = false;
    }
}

onMounted(() => {
    loadStripe();
});
</script>

<template>
    <Head title="Top Up Wallet - MK Network" />
    <div class="max-w-2xl mx-auto space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white mb-1">Top Up Wallet</h1>
            <p class="text-dark-300">Add funds to your wallet balance</p>
        </div>

        <!-- Current Balance -->
        <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6">
            <div class="text-sm text-dark-300 mb-1">Current Wallet Balance</div>
            <div class="text-4xl font-bold text-white">
                £ {{ Number(wallet.balance).toFixed(2) }}
            </div>
            <div class="text-sm text-dark-400 mt-1">
                Available: £ {{ Number(availableBalance).toFixed(2) }}
            </div>
        </div>

        <!-- Error Message -->
        <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-300 text-sm"
        >
            {{ errorMessage }}
        </div>

        <!-- Amount Selection -->
        <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Select Amount</h3>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <button
                    v-for="amount in presetAmounts"
                    :key="amount"
                    type="button"
                    @click="selectAmount(amount)"
                    :class="[
                        'py-4 rounded-xl font-semibold text-lg transition',
                        selectedAmount === amount
                            ? 'bg-primary text-white border-2 border-primary'
                            : 'bg-dark-700 text-white border-2 border-dark-600 hover:border-primary',
                    ]"
                >
                    £ {{ amount }}
                </button>
            </div>

            <!-- Custom Amount -->
            <div>
                <label class="block text-sm font-medium text-dark-200 mb-2">Or enter custom amount</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-dark-400 font-medium">£</span>
                    <input
                        v-model="customAmount"
                        type="number"
                        min="10"
                        max="50000"
                        step="1"
                        placeholder="Enter amount (min £10)"
                        class="w-full border border-dark-600 rounded-lg pl-8 pr-4 py-3 bg-dark-700 text-white outline-none focus:border-primary"
                        @input="selectedAmount = null"
                    />
                </div>
            </div>
        </div>

        <!-- Card Details -->
        <div v-if="stripeLoaded" class="bg-dark-800 rounded-2xl border border-dark-600 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Card Details</h3>
            <div
                ref="cardElement"
                class="bg-dark-700 border border-dark-600 rounded-lg p-4 min-h-[48px]"
            ></div>
            <p class="text-xs text-dark-400 mt-2">
                🔒 Secured by Stripe. We do not store your card details.
            </p>
        </div>

        <div v-else-if="errorMessage" class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-300 text-sm">
            {{ errorMessage }}
        </div>

        <div v-else class="bg-dark-800 rounded-2xl border border-dark-600 p-6">
            <div class="flex items-center gap-3">
                <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                <span class="text-dark-300">Loading secure payment form...</span>
            </div>
        </div>

        <!-- Pay Button -->
        <button
            type="button"
            @click="initiateTopUp"
            :disabled="processing || !stripeLoaded"
            class="w-full py-4 bg-primary text-white rounded-xl font-semibold hover:bg-primary-dark disabled:opacity-50 transition text-base"
        >
            {{ processing ? 'Processing...' : `Pay £${getAmount()} with Card` }}
        </button>
    </div>
</template>
