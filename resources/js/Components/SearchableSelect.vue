<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    options: { type: Array, default: () => [] }, // [{ id, label, hint? }]
    allLabel: { type: String, default: "All" },
    searchPlaceholder: { type: String, default: "Search..." },
});
const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const query = ref("");
const root = ref(null);

const selectedLabel = computed(
    () => props.options.find((o) => o.id == props.modelValue)?.label || "",
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(q) ||
            (o.hint && o.hint.toLowerCase().includes(q)),
    );
});

function choose(id) {
    emit("update:modelValue", id);
    open.value = false;
    query.value = "";
}

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
        query.value = "";
    }
}

onMounted(() => document.addEventListener("click", onClickOutside));
onBeforeUnmount(() => document.removeEventListener("click", onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            @click="open = !open"
            class="border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm min-w-[190px] text-left flex items-center justify-between gap-2"
        >
            <span class="truncate">{{ selectedLabel || allLabel }}</span>
            <svg
                class="w-4 h-4 text-ink-500 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-72 bg-surface-3 border border-surface-3 rounded-lg shadow-xl max-h-80 flex flex-col"
        >
            <div class="p-2 border-b border-surface-3">
                <input
                    v-model="query"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full border border-surface-3 rounded px-3 py-2 bg-surface-2 text-ink-900 text-sm outline-none"
                    autofocus
                />
            </div>
            <div class="overflow-y-auto flex-1">
                <div
                    @click="choose('')"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-surface-2 transition"
                    :class="
                        !modelValue
                            ? 'text-primary font-medium'
                            : 'text-ink-700'
                    "
                >
                    {{ allLabel }}
                </div>
                <div
                    v-for="o in filtered"
                    :key="o.id"
                    @click="choose(o.id)"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-surface-2 transition flex items-center gap-2"
                    :class="
                        modelValue == o.id
                            ? 'bg-surface-2 text-primary font-medium'
                            : 'text-ink-700'
                    "
                >
                    <span class="truncate">{{ o.label }}</span>
                    <span
                        v-if="o.hint"
                        class="text-ink-500 text-xs ml-auto shrink-0"
                        >{{ o.hint }}</span
                    >
                </div>
                <div
                    v-if="!filtered.length"
                    class="px-3 py-4 text-center text-ink-500 text-sm"
                >
                    No results
                </div>
            </div>
        </div>
    </div>
</template>
