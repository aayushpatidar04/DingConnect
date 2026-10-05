<script setup>
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-surface-1 pt-6 sm:justify-center sm:pt-0"
    >

        <div
            class="mt-6 w-full overflow-hidden bg-surface-0 border border-surface-3 px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
