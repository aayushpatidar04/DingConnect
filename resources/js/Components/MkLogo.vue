<script setup>
import logo from "@/assets/images/logo.png";

defineOptions({ name: "MkLogo" });

defineProps({
    size: {
        type: String,
        default: "md",
    },
});
</script>

<template>
    <img
        :src="logo"
        alt="MK Network Communications"
        class="w-auto object-contain"
        :class="[
            size === 'sm'
                ? 'h-8'
                : size === 'md'
                  ? 'h-10'
                  : size === 'lg'
                    ? 'h-14'
                    : size === 'xl'
                      ? 'h-20'
                      : 'h-10',
        ]"
    />
</template>
