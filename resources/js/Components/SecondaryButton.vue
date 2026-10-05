<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-surface-3 bg-surface-0 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink-700 shadow-sm transition duration-150 ease-in-out hover:bg-surface-1 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-25 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
    >
        <slot />
    </button>
</template>
