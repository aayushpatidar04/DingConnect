<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] }, // [{ id, label }]
    placeholder: { type: String, default: "Search providers..." },
    emptyText: { type: String, default: "Select providers..." },
});

const emit = defineEmits(["update:modelValue"]);

const isOpen = ref(false);
const search = ref("");
const dropdownRef = ref(null);

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
}

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

const displayText = computed(() => {
    if (props.modelValue.length === 0) return props.emptyText;
    if (props.modelValue.length === props.options.length) return "All providers selected";
    return `${props.modelValue.length} provider(s) selected`;
});
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <!-- Dropdown Toggle Button -->
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 flex items-center justify-between focus:outline-none focus:border-primary"
        >
            <span class="truncate">{{ displayText }}</span>
            <svg
                class="w-4 h-4 text-ink-500 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Panel -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-2 w-full bg-surface-2 border border-surface-3 rounded-xl shadow-xl overflow-hidden"
        >
            <div class="p-2 border-b border-surface-3 flex items-center gap-2 bg-surface-3/50">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="placeholder"
                    class="flex-1 min-w-0 bg-transparent text-sm text-ink-900 outline-none px-2 py-1"
                />
                <button
                    type="button"
                    @click="selectShown"
                    class="text-xs text-primary font-medium hover:underline whitespace-nowrap px-1"
                >
                    Select shown
                </button>
                <button
                    type="button"
                    @click="clearAll"
                    class="text-xs text-ink-500 hover:text-ink-900 whitespace-nowrap px-1"
                >
                    Clear
                </button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2 space-y-1">
                <label
                    v-for="o in filtered"
                    :key="o.id"
                    class="flex items-center gap-2 text-sm text-ink-800 cursor-pointer px-2 py-1 rounded hover:bg-surface-3/50 transition"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue.includes(o.id)"
                        @change="toggle(o.id)"
                        class="rounded border-surface-3 text-primary focus:ring-primary"
                    />
                    <span class="truncate">{{ o.label }}</span>
                </label>
                <p v-if="!filtered.length" class="text-xs text-ink-500 px-2 py-2 text-center">
                    No matches found
                </p>
            </div>
            <div class="px-3 py-2 border-t border-surface-3 text-xs text-ink-500 bg-surface-3/30 flex justify-between items-center">
                <span>{{ modelValue.length }} selected</span>
                <button
                    type="button"
                    @click="isOpen = false"
                    class="text-primary font-medium hover:underline"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
