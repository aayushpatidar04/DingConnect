<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: "" },
    maxWidth: { type: String, default: "max-w-lg" },
});

defineEmits(["close"]);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4"
        @click.self="$emit('close')"
    >
        <div
            class="w-full bg-surface-2 border border-surface-3 rounded-2xl p-6 my-8"
            :class="maxWidth"
        >
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-ink-900">{{ title }}</h3>
                <button
                    type="button"
                    @click="$emit('close')"
                    class="text-ink-500 hover:text-ink-900 text-2xl leading-none"
                    aria-label="Close"
                >
                    ×
                </button>
            </div>
            <slot />
        </div>
    </div>
</template>
