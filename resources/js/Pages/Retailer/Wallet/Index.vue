<script setup>
import { Head, Link } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
defineOptions({ layout: RetailerLayout });

const props = defineProps({
    wallet: Object,
    availableBalance: Number,
    topups: Object,
});
</script>

<template>
    <Head title="Wallet - MK Network" />
    <div class="space-y-6">
        <h1 class="text-3xl font-bold text-ink-900 mb-1">My Wallet</h1>
        <p class="text-ink-500 mb-6">
            Manage your wallet balance and top-up history
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="stat-gradient-1 rounded-2xl p-6 card-hover">
                <div class="text-sm text-blue-100">Wallet Balance</div>
                <div class="text-3xl font-bold text-ink-900 mt-1">
                    £ {{ Number(wallet.balance).toFixed(2) }}
                </div>
            </div>
            <div class="stat-gradient-2 rounded-2xl p-6 card-hover">
                <div class="text-sm text-green-100">Available Balance</div>
                <div class="text-3xl font-bold text-ink-900 mt-1">
                    £ {{ Number(availableBalance).toFixed(2) }}
                </div>
            </div>
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 flex items-center justify-between"
            >
                <div>
                    <div class="text-sm text-ink-500">Need More Balance?</div>
                    <div class="text-sm text-ink-500 mt-1">
                        Top up your wallet instantly
                    </div>
                </div>
                <Link
                    href="/retailer/wallet/topup"
                    class="btn-primary px-6 py-3 bg-primary text-ink-900 rounded-xl font-medium"
                    >Top Up</Link
                >
            </div>
        </div>

        <!-- Top Up History -->
        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="p-6 border-b border-surface-3">
                <h3 class="text-lg font-semibold text-ink-900">Top-Up History</h3>
            </div>
            <div v-if="topups.data?.length" class="divide-y divide-dark-600">
                <div
                    v-for="topup in topups.data"
                    :key="topup.id"
                    class="p-4 flex items-center justify-between hover:bg-surface-2 transition"
                >
                    <div>
                        <div class="font-medium text-ink-900">
                            £ {{ Number(topup.amount).toFixed(2) }}
                        </div>
                        <div class="text-sm text-ink-500">
                            {{ topup.payment_method }} · {{ topup.created_at }}
                        </div>
                    </div>
                    <span
                        :class="[
                            'px-3 py-1 text-xs rounded-full font-medium',
                            topup.status === 'completed'
                                ? 'bg-green-500/20 text-accent-light'
                                : topup.status === 'failed'
                                  ? 'bg-red-500/20 text-red-600'
                                  : 'bg-yellow-500/20 text-yellow-600',
                        ]"
                    >
                        {{ topup.status }}
                    </span>
                </div>
            </div>
            <div v-else class="p-8 text-center text-ink-500">
                No top-ups yet
            </div>
        </div>
    </div>
</template>
