<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-primary dark:border-primary-dark text-start text-base font-medium text-primary-dark dark:text-primary-light bg-primary/10 dark:bg-indigo-900/50 focus:outline-none focus:text-primary-dark focus:bg-primary/20 dark:focus:bg-indigo-900 focus:border-primary-dark transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-ink-500 hover:text-ink-700 hover:bg-surface-2 hover:border-surface-3 focus:outline-none focus:text-ink-700 focus:bg-surface-2 focus:border-surface-3 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
