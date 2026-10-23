<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] }, // [{ id, label }]
    placeholder: { type: String, default: "Search..." },
    emptyText: { type: String, default: "Nothing selected" },
});

const emit = defineEmits(["update:modelValue"]);

const search = ref("");

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.options.filter((o) => o.label.toLowerCase().includes(q))
        : props.options;
});

function toggle(id) {
    const next = props.modelValue.includes(id)
        ? props.modelValue.filter((v) => v !== id)
        : [...props.modelValue, id];
    emit("update:modelValue", next);
}

function selectShown() {
    emit("update:modelValue", [
        ...new Set([...props.modelValue, ...filtered.value.map((o) => o.id)]),
    ]);
}

function clearAll() {
    emit("update:modelValue", []);
}
</script>

<template>
    <div class="border border-surface-3 rounded-lg bg-surface-3">
        <div class="p-2 border-b border-surface-2 flex items-center gap-2">
            <input
                v-model="search"
                type="text"
                :placeholder="placeholder"
                class="flex-1 min-w-0 bg-transparent text-sm text-ink-900 outline-none px-2 py-1"
            />
            <button
                type="button"
                @click="selectShown"
                class="text-xs text-primary-light hover:text-primary whitespace-nowrap"
            >
                Select shown
            </button>
            <button
                type="button"
                @click="clearAll"
                class="text-xs text-ink-500 hover:text-ink-900 whitespace-nowrap"
            >
                Clear
            </button>
        </div>
        <div class="max-h-40 overflow-y-auto p-2 space-y-1">
            <label
                v-for="o in filtered"
                :key="o.id"
                class="flex items-center gap-2 text-sm text-ink-700 cursor-pointer px-1 py-0.5 rounded hover:bg-surface-2"
            >
                <input
                    type="checkbox"
                    :checked="modelValue.includes(o.id)"
                    @change="toggle(o.id)"
                    class="rounded border-surface-3"
                />
                <span class="truncate">{{ o.label }}</span>
            </label>
            <p v-if="!filtered.length" class="text-xs text-ink-500 px-1">
                No matches
            </p>
        </div>
        <div class="px-3 py-1.5 border-t border-surface-2 text-xs text-ink-500">
            {{ modelValue.length ? `${modelValue.length} selected` : emptyText }}
        </div>
    </div>
</template>
