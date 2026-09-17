<script setup>
import { useToast, toasts } from "@/composables/useToast.js";

// Keep the reactive reference alive in this component
const { removeToast } = useToast();

function getIcon(type) {
    switch (type) {
        case "success":
            return "M5 13l4 4L19 7";
        case "error":
            return "M6 18L18 6M6 6l12 12";
        case "warning":
            return "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z";
        default:
            return "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z";
    }
}

function getColors(type) {
    switch (type) {
        case "success":
            return "bg-accent/90 border-accent text-white";
        case "error":
            return "bg-red-500/90 border-red-400 text-white";
        case "warning":
            return "bg-yellow-500/90 border-yellow-400 text-white";
        default:
            return "bg-primary/90 border-primary text-white";
    }
}
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-3 max-w-sm">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="[
                'flex items-center gap-3 px-5 py-4 rounded-xl border shadow-2xl backdrop-blur-sm transform transition-all duration-500',
                getColors(toast.type),
            ]"
            style="animation: slideInRight 0.3s ease-out"
        >
            <!-- Icon -->
            <svg
                class="w-5 h-5 flex-shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="getIcon(toast.type)"
                />
            </svg>

            <!-- Message -->
            <p class="text-sm font-medium flex-1">{{ toast.message }}</p>

            <!-- Close -->
            <button
                @click="removeToast(toast.id)"
                class="flex-shrink-0 hover:opacity-70 transition"
            >
                <svg
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </button>
        </div>
    </div>
</template>
