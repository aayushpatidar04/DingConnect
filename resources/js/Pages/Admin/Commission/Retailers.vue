<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";
import CommissionTabs from "@/Components/CommissionTabs.vue";
import CommissionModal from "@/Components/CommissionModal.vue";
import { changeTypes } from "@/Composables/useCommission";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    retailers: Object,
    levels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters.search ?? "",
    level: props.filters.level ?? "",
});

let timer = null;
watch(
    filters,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                "/admin/retailer-levels",
                Object.fromEntries(
                    Object.entries(filters).filter(([, v]) => v !== ""),
                ),
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);
    },
    { deep: true },
);

/* ---------- selection / bulk ---------- */
const selectedIds = ref([]);
const allOnPage = computed(
    () =>
        props.retailers.data.length > 0 &&
        props.retailers.data.every((r) => selectedIds.value.includes(r.id)),
);

function toggleAll() {
    if (allOnPage.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.retailers.data.map((r) => r.id);
    }
}

/* ---------- change level (single or bulk) ---------- */
const showModal = ref(false);
const target = ref(null); // retailer row, or null for bulk
const form = useForm({
    level_id: "",
    auto_level_enabled: true,
    actual_connections: "",
    reason: "",
});

const chosenLevel = computed(
    () => props.levels.find((l) => l.id === Number(form.level_id)) ?? null,
);

function openSingle(retailer) {
    target.value = retailer;
    form.clearErrors();
    form.level_id = retailer.level_id ?? "";
    form.auto_level_enabled = retailer.level_id
        ? retailer.auto_level_enabled
        : true;
    form.actual_connections = retailer.actual_connections;
    form.reason = "";
    showModal.value = true;
}

function openBulk() {
    target.value = null;
    form.clearErrors();
    form.level_id = "";
    form.auto_level_enabled = true;
    form.actual_connections = "";
    form.reason = "";
    showModal.value = true;
}

function submit() {
    const done = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            selectedIds.value = [];
        },
    };

    if (target.value) {
        form.transform((d) => ({
            ...d,
            level_id: d.level_id || null,
            actual_connections:
                d.actual_connections === "" ? null : d.actual_connections,
        })).put(`/admin/retailer-levels/${target.value.id}`, done);
    } else {
        form.transform((d) => ({
            level_id: d.level_id || null,
            auto_level_enabled: d.auto_level_enabled,
            reason: d.reason,
            retailer_ids: selectedIds.value,
        })).post("/admin/retailer-levels/bulk", done);
    }
}

/* ---------- history ---------- */
const showHistory = ref(false);
const historyRetailer = ref(null);
const history = ref([]);
const historyLoading = ref(false);

async function openHistory(retailer) {
    historyRetailer.value = retailer;
    history.value = [];
    historyLoading.value = true;
    showHistory.value = true;
    try {
        const res = await window.axios.get(
            `/admin/retailer-levels/${retailer.id}/history`,
        );
        history.value = res.data.history;
    } finally {
        historyLoading.value = false;
    }
}

const formatDate = (iso) =>
    iso
        ? new Date(iso).toLocaleString("en-GB", {
              timeZone: "Europe/London",
              day: "2-digit",
              month: "short",
              year: "numeric",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "-";

const inputClass =
    "w-full border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none";
const labelClass = "block text-xs font-medium text-ink-500 mb-1";
const thClass =
    "px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap";
</script>

<template>
    <Head title="Retailer Levels - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Commission</h1>
            <p class="text-ink-500">
                Choose which level each retailer is on. Retailers without a
                level earn no commission.
            </p>
        </div>

        <CommissionTabs />

        <div class="flex flex-wrap items-center gap-3">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search retailers..."
                class="border border-surface-3 rounded-lg px-4 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
            />
            <select
                v-model="filters.level"
                class="border border-surface-3 rounded-lg px-4 py-2 bg-surface-3 text-ink-900 text-sm outline-none"
            >
                <option value="">All levels</option>
                <option value="none">No level</option>
                <option v-for="l in levels" :key="l.id" :value="l.id">
                    {{ l.name }}
                </option>
            </select>
            <button
                v-if="selectedIds.length"
                type="button"
                @click="openBulk"
                class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
            >
                Set level for {{ selectedIds.length }} selected
            </button>
        </div>

        <div
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input
                                    type="checkbox"
                                    :checked="allOnPage"
                                    @change="toggleAll"
                                    class="rounded border-surface-3"
                                />
                            </th>
                            <th :class="thClass">Retailer</th>
                            <th :class="thClass">Level</th>
                            <th :class="thClass">Actual connections</th>
                            <th :class="thClass">Level connections</th>
                            <th :class="thClass">Next level</th>
                            <th :class="thClass"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="r in retailers.data" :key="r.id">
                            <td class="px-4 py-3">
                                <input
                                    v-model="selectedIds"
                                    :value="r.id"
                                    type="checkbox"
                                    class="rounded border-surface-3"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/retailers/${r.id}`"
                                    class="text-sm font-medium text-ink-900 hover:text-primary"
                                    >{{ r.name }}</Link
                                >
                                <div class="text-xs text-ink-500">
                                    {{ r.shop_name || r.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-if="r.level_name"
                                    class="px-2 py-0.5 rounded-full text-xs bg-primary/15 text-primary-light"
                                    >{{ r.level_name }}</span
                                >
                                <span v-else class="text-ink-500">No level</span>
                                <span
                                    v-if="r.level_name && !r.auto_level_enabled"
                                    class="ml-2 text-[11px] text-ink-500"
                                    >locked</span
                                >
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ r.actual_connections }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ r.level_name ? r.level_connections : "—" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                <template
                                    v-if="r.level_name && r.auto_level_enabled && r.next_level_name"
                                >
                                    {{ r.next_level_name }} at
                                    {{ r.next_level_min }}
                                    <span class="text-ink-500"
                                        >({{
                                            Math.max(
                                                r.next_level_min -
                                                    r.level_connections,
                                                0,
                                            )
                                        }}
                                        to go)</span
                                    >
                                </template>
                                <span v-else class="text-ink-500">—</span>
                            </td>
                            <td
                                class="px-4 py-3 text-right text-sm whitespace-nowrap"
                            >
                                <button
                                    type="button"
                                    @click="openSingle(r)"
                                    class="text-primary-light hover:text-primary mr-3"
                                >
                                    Change level
                                </button>
                                <button
                                    type="button"
                                    @click="openHistory(r)"
                                    class="text-ink-500 hover:text-ink-900"
                                >
                                    History
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!retailers.data.length"
                class="p-8 text-center text-ink-500"
            >
                No retailers found
            </div>
            <div
                v-if="retailers.links"
                class="flex justify-center gap-2 p-4 flex-wrap"
            >
                <template v-for="link in retailers.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm border border-surface-3 transition"
                        :class="
                            link.active
                                ? 'bg-primary text-ink-100 border-primary'
                                : 'bg-surface-2 text-ink-500 hover:bg-surface-3'
                        "
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm bg-surface-2 text-ink-500"
                    />
                </template>
            </div>
        </div>
    </div>

    <!-- Change level -->
    <CommissionModal
        :show="showModal"
        :title="
            target
                ? `Change level – ${target.name}`
                : `Set level for ${selectedIds.length} retailers`
        "
        @close="showModal = false"
    >
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label :class="labelClass">Level</label>
                <select v-model="form.level_id" :class="inputClass">
                    <option value="">No level (no commission)</option>
                    <option v-for="l in levels" :key="l.id" :value="l.id">
                        {{ l.name }} (from {{ l.min_connections }}
                        connections)
                    </option>
                </select>
                <InputError :message="form.errors.level_id" />
                <p class="text-xs text-ink-500 mt-1">
                    <template v-if="chosenLevel">
                        Level connections will be set to
                        <strong>{{ chosenLevel.min_connections }}</strong
                        >. Actual connections stay as they are.
                    </template>
                    <template v-else>
                        The retailer stops earning commission and automatic
                        upgrades are switched off.
                    </template>
                </p>
            </div>

            <label
                v-if="chosenLevel"
                class="flex items-start gap-2 text-sm text-ink-700"
            >
                <input
                    v-model="form.auto_level_enabled"
                    type="checkbox"
                    class="mt-0.5 rounded border-surface-3"
                />
                <span
                    >Upgrade automatically as new connections come in. Untick
                    to keep the retailer on this level until you change it.</span
                >
            </label>

            <div v-if="target">
                <label :class="labelClass"
                    >Actual connections (opening count)</label
                >
                <input
                    v-model="form.actual_connections"
                    type="number"
                    min="0"
                    :class="inputClass"
                />
                <InputError :message="form.errors.actual_connections" />
            </div>

            <div>
                <label :class="labelClass">Reason (optional)</label>
                <input v-model="form.reason" type="text" :class="inputClass" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    @click="showModal = false"
                    class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60"
                >
                    Save
                </button>
            </div>
        </form>
    </CommissionModal>

    <!-- History -->
    <CommissionModal
        :show="showHistory"
        :title="`Level history – ${historyRetailer?.name ?? ''}`"
        max-width="max-w-2xl"
        @close="showHistory = false"
    >
        <div v-if="historyLoading" class="text-ink-500 text-sm">Loading…</div>
        <div v-else-if="!history.length" class="text-ink-500 text-sm">
            No level changes yet.
        </div>
        <ul v-else class="divide-y divide-surface-3">
            <li v-for="h in history" :key="h.id" class="py-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <span class="font-medium text-ink-900">
                        {{ changeTypes[h.type] ?? h.type }}:
                        {{ h.from ?? "No level" }} → {{ h.to ?? "No level" }}
                    </span>
                    <span class="text-ink-500">{{ formatDate(h.at) }}</span>
                </div>
                <div class="text-xs text-ink-500 mt-0.5">
                    Level connections {{ h.level_connections_before }} →
                    {{ h.level_connections_after }} · actual
                    {{ h.actual_connections }} · by {{ h.by }}
                    <template v-if="h.reason"> · {{ h.reason }}</template>
                </div>
            </li>
        </ul>
    </CommissionModal>
</template>
