<script setup>
defineProps({
    benefits: { type: Array, default: () => [] },
    configured: Boolean,
    loading: Boolean,
    error: String,
});
</script>

<template>
    <section class="rounded-xl border border-primary/20 bg-primary/5 p-4">
        <h3 class="mb-2 text-sm font-semibold text-ink-900">Plan benefits</h3>
        <p v-if="loading" class="text-sm text-ink-500">
            Loading plan details...
        </p>
        <p v-else-if="error" class="text-sm text-red-600">
            {{ error }}
        </p>
        <ul v-else-if="benefits.length" class="list-disc space-y-1 pl-5 text-sm text-ink-700">
            <li v-for="(benefit, index) in benefits" :key="`${index}-${benefit}`">
                {{ benefit }}
            </li>
        </ul>
        <p v-else-if="configured" class="text-sm text-ink-500">
            No benefits have been added to this plan yet.
        </p>
        <p v-else class="text-sm text-ink-500">
            Benefit details have not been configured for this price yet.
        </p>
    </section>
</template>
