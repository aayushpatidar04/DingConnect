<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-surface-3 bg-surface-0 text-ink-900 shadow-sm focus:border-primary focus:ring-primary"
        v-model="model"
        ref="input"
    />
</template>
