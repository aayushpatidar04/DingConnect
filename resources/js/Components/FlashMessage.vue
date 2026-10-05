<script setup>
import { computed, ref, watch, onBeforeUnmount } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const message = ref(null);
const type = ref("error");
let timer = null;

const flash = computed(() => page.props.flash ?? {});

watch(
    flash,
    (f) => {
        const text = f.error || f.success;
        if (!text) return;
        type.value = f.error ? "error" : "success";
        message.value = text;
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 8000);
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0 translate-y-4"
    >
        <div
            v-if="message"
            :class="[
                'fixed bottom-4 right-4 z-50 max-w-sm flex items-start gap-3 rounded-lg px-4 py-3 text-sm shadow-lg border bg-surface-2',
                type === 'error'
                    ? 'border-red-500/40 text-red-600'
                    : 'border-accent/40 text-accent-light',
            ]"
            role="alert"
        >
            <span class="flex-1">{{ message }}</span>
            <button
                type="button"
                class="hover:text-ink-900 leading-none"
                aria-label="Dismiss"
                @click="message = null"
            >
                &times;
            </button>
        </div>
    </Transition>
</template>
