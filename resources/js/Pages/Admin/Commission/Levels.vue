<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import CommissionTabs from "@/Components/CommissionTabs.vue";
import CommissionModal from "@/Components/CommissionModal.vue";
import {
    money,
    amountRange,
    stepsText,
    firstTotal,
    lifetimeTotal,
} from "@/Composables/useCommission";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    levels: { type: Array, default: () => [] },
    selectedLevelId: { type: Number, default: null },
    rates: { type: Array, default: () => [] },
    operators: { type: Array, default: () => [] },
});

const selected = computed(
    () => props.levels.find((l) => l.id === props.selectedLevelId) ?? null,
);

function rangeLabel(level) {
    const next = props.levels
        .filter((l) => l.min_connections > level.min_connections)
        .sort((a, b) => a.min_connections - b.min_connections)[0];
    return next
        ? `${level.min_connections}–${next.min_connections - 1} connections`
        : `${level.min_connections}+ connections`;
}

function selectLevel(id) {
    router.get(
        "/admin/commission-levels",
        { level: id },
        { preserveState: true, preserveScroll: true },
    );
}

/* ---------- level modal ---------- */
const showLevelModal = ref(false);
const levelForm = useForm({
    id: null,
    name: "",
    min_connections: 0,
    is_active: true,
});

function openLevel(level = null) {
    levelForm.clearErrors();
    levelForm.id = level?.id ?? null;
    levelForm.name = level?.name ?? "";
    levelForm.min_connections = level?.min_connections ?? 0;
    levelForm.is_active = level?.is_active ?? true;
    showLevelModal.value = true;
}

function submitLevel() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showLevelModal.value = false),
    };
    if (levelForm.id) {
        levelForm.put(`/admin/commission-levels/${levelForm.id}`, options);
    } else {
        levelForm.post("/admin/commission-levels", options);
    }
}

function deleteLevel(level) {
    if (!confirm(`Delete ${level.name} and all of its rates?`)) return;
    router.delete(`/admin/commission-levels/${level.id}`);
}

/* ---------- copy rates ---------- */
const showCopyModal = ref(false);
const copySource = ref("");

function submitCopy() {
    if (!copySource.value || !selected.value) return;
    router.post(
        `/admin/commission-levels/${selected.value.id}/copy-rates`,
        { source_level_id: copySource.value },
        {
            preserveScroll: true,
            onSuccess: () => (showCopyModal.value = false),
        },
    );
}

/* ---------- rate modal ---------- */
const showRateModal = ref(false);
const blankStep = (from = 2) => ({
    from_sequence: from,
    to_sequence: "",
    amount: "",
});

const rateForm = useForm({
    id: null,
    commission_level_id: null,
    operator_id: "",
    min_amount: 0,
    max_amount: "",
    first_topup_amount: 0,
    first_bonus_amount: 0,
    is_active: true,
    steps: [blankStep()],
});

function openRate(rate = null) {
    rateForm.clearErrors();
    rateForm.id = rate?.id ?? null;
    rateForm.commission_level_id = selected.value?.id ?? null;
    rateForm.operator_id = rate?.operator_id ?? "";
    rateForm.min_amount = rate?.min_amount ?? 0;
    rateForm.max_amount = rate?.max_amount ?? "";
    rateForm.first_topup_amount = rate?.first_topup_amount ?? 0;
    rateForm.first_bonus_amount = rate?.first_bonus_amount ?? 0;
    rateForm.is_active = rate?.is_active ?? true;
    rateForm.steps = rate?.steps?.length
        ? rate.steps.map((s) => ({
              from_sequence: s.from_sequence,
              to_sequence: s.to_sequence ?? "",
              amount: s.amount,
          }))
        : [blankStep()];
    showRateModal.value = true;
}

function addStep() {
    const last = rateForm.steps[rateForm.steps.length - 1];
    const next = last && last.to_sequence ? Number(last.to_sequence) + 1 : 2;
    rateForm.steps.push(blankStep(next));
}

function removeStep(index) {
    rateForm.steps.splice(index, 1);
}

function submitRate() {
    rateForm
        .transform((data) => ({
            ...data,
            operator_id: data.operator_id || null,
            max_amount: data.max_amount === "" ? null : data.max_amount,
        }))
        .submit(
            rateForm.id ? "put" : "post",
            rateForm.id
                ? `/admin/commission-rates/${rateForm.id}`
                : "/admin/commission-rates",
            {
                preserveScroll: true,
                onSuccess: () => (showRateModal.value = false),
            },
        );
}

function deleteRate(rate) {
    if (!confirm("Delete this rate?")) return;
    router.delete(`/admin/commission-rates/${rate.id}`, {
        preserveScroll: true,
    });
}

const preview = computed(() => ({
    first_topup_amount: rateForm.first_topup_amount,
    first_bonus_amount: rateForm.first_bonus_amount,
    steps: rateForm.steps.filter((s) => s.amount !== ""),
}));

const inputClass =
    "w-full border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none";
const labelClass = "block text-xs font-medium text-ink-500 mb-1";
</script>

<template>
    <Head title="Commission Levels - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Commission</h1>
            <p class="text-ink-500">
                Cashback paid to retailers on every successful recharge, by
                level, operator and amount range.
            </p>
        </div>

        <CommissionTabs />

        <!-- Level selector -->
        <div class="flex flex-wrap items-stretch gap-3">
            <button
                v-for="level in levels"
                :key="level.id"
                type="button"
                @click="selectLevel(level.id)"
                class="text-left px-4 py-3 rounded-xl border transition min-w-[170px]"
                :class="
                    level.id === selectedLevelId
                        ? 'bg-primary/10 border-primary text-ink-900'
                        : 'bg-surface-2 border-surface-3 text-ink-700 hover:border-primary/50'
                "
            >
                <div class="flex items-center gap-2">
                    <span class="font-semibold">{{ level.name }}</span>
                    <span
                        v-if="!level.is_active"
                        class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-surface-3 text-ink-500"
                        >Inactive</span
                    >
                </div>
                <div class="text-xs text-ink-500 mt-0.5">
                    {{ rangeLabel(level) }}
                </div>
                <div class="text-xs text-ink-500">
                    {{ level.retailers_count }} retailer{{
                        level.retailers_count === 1 ? "" : "s"
                    }}
                    · {{ level.rates_count }} rate{{
                        level.rates_count === 1 ? "" : "s"
                    }}
                </div>
            </button>

            <button
                type="button"
                @click="openLevel()"
                class="px-4 py-3 rounded-xl border border-dashed border-surface-3 text-ink-500 hover:text-ink-900 hover:border-primary transition text-sm font-medium"
            >
                + New level
            </button>
        </div>

        <div
            v-if="!levels.length"
            class="bg-surface-2 rounded-2xl border border-surface-3 p-8 text-center text-ink-500"
        >
            No levels yet. Create one, or run the commission seeder to load the
            starting levels and rates.
        </div>

        <!-- Selected level -->
        <div
            v-if="selected"
            class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
        >
            <div
                class="p-4 border-b border-surface-3 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h2 class="text-xl font-semibold text-ink-900">
                        {{ selected.name }}
                    </h2>
                    <p class="text-sm text-ink-500">
                        {{ rangeLabel(selected) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="openLevel(selected)"
                        class="px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3 transition"
                    >
                        Edit level
                    </button>
                    <button
                        v-if="!rates.length && levels.length > 1"
                        type="button"
                        @click="showCopyModal = true"
                        class="px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3 transition"
                    >
                        Copy rates from…
                    </button>
                    <button
                        type="button"
                        @click="deleteLevel(selected)"
                        class="px-3 py-2 border border-red-500/40 rounded-lg text-sm text-red-600 hover:bg-red-500/10 transition"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        @click="openRate()"
                        class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
                    >
                        + Add rate
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th
                                v-for="h in [
                                    'Operator',
                                    'Top-up amount',
                                    'Top up',
                                    'Bonus',
                                    'First total',
                                    'Ongoing top-ups',
                                    'Lifetime total',
                                    'Status',
                                    '',
                                ]"
                                :key="h"
                                class="px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap"
                            >
                                {{ h }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="rate in rates" :key="rate.id">
                            <td class="px-4 py-3 text-sm text-ink-900">
                                {{ rate.operator?.name ?? "All operators" }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm text-ink-700 whitespace-nowrap"
                            >
                                {{ amountRange(rate) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ money(rate.first_topup_amount) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ money(rate.first_bonus_amount) }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-medium text-ink-900"
                            >
                                {{ money(firstTotal(rate)) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                {{ stepsText(rate) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-700">
                                <template v-if="lifetimeTotal(rate) !== null">
                                    {{ money(lifetimeTotal(rate)) }}
                                </template>
                                <span v-else class="text-ink-500"
                                    >No limit</span
                                >
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs"
                                    :class="
                                        rate.is_active
                                            ? 'bg-green-500/20 text-green-600'
                                            : 'bg-surface-3 text-ink-500'
                                    "
                                    >{{
                                        rate.is_active ? "Active" : "Off"
                                    }}</span
                                >
                            </td>
                            <td
                                class="px-4 py-3 text-right text-sm whitespace-nowrap"
                            >
                                <button
                                    type="button"
                                    @click="openRate(rate)"
                                    class="text-primary-light hover:text-primary mr-3"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="deleteRate(rate)"
                                    class="text-red-600 hover:text-red-500"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!rates.length" class="p-8 text-center text-ink-500">
                No rates on this level yet. Retailers on it earn nothing until
                you add some.
            </div>
        </div>
    </div>

    <!-- Level modal -->
    <CommissionModal
        :show="showLevelModal"
        :title="levelForm.id ? 'Edit level' : 'New level'"
        @close="showLevelModal = false"
    >
        <form @submit.prevent="submitLevel" class="space-y-4">
            <div>
                <label :class="labelClass">Name</label>
                <input v-model="levelForm.name" type="text" :class="inputClass" />
                <InputError :message="levelForm.errors.name" />
            </div>
            <div>
                <label :class="labelClass"
                    >Starts at (connections)</label
                >
                <input
                    v-model="levelForm.min_connections"
                    type="number"
                    min="0"
                    :class="inputClass"
                />
                <p class="text-xs text-ink-500 mt-1">
                    A retailer reaches this level when their level connections
                    pass this number. The level ends where the next level
                    begins.
                </p>
                <InputError :message="levelForm.errors.min_connections" />
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input
                    v-model="levelForm.is_active"
                    type="checkbox"
                    class="rounded border-surface-3"
                />
                Active (inactive levels pay nothing and are skipped by auto
                upgrade)
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    @click="showLevelModal = false"
                    class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="levelForm.processing"
                    class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60"
                >
                    Save
                </button>
            </div>
        </form>
    </CommissionModal>

    <!-- Copy modal -->
    <CommissionModal
        :show="showCopyModal"
        title="Copy rates"
        @close="showCopyModal = false"
    >
        <div class="space-y-4">
            <p class="text-sm text-ink-500">
                Copy every rate and ongoing band from another level into
                {{ selected?.name }}. You can edit them afterwards.
            </p>
            <select v-model="copySource" :class="inputClass">
                <option value="">Choose a level…</option>
                <option
                    v-for="l in levels.filter((l) => l.id !== selected?.id)"
                    :key="l.id"
                    :value="l.id"
                >
                    {{ l.name }} ({{ l.rates_count }} rates)
                </option>
            </select>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    @click="showCopyModal = false"
                    class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="submitCopy"
                    :disabled="!copySource"
                    class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60"
                >
                    Copy
                </button>
            </div>
        </div>
    </CommissionModal>

    <!-- Rate modal -->
    <CommissionModal
        :show="showRateModal"
        :title="
            (rateForm.id ? 'Edit rate' : 'Add rate') +
            (selected ? ` – ${selected.name}` : '')
        "
        max-width="max-w-2xl"
        @close="showRateModal = false"
    >
        <form @submit.prevent="submitRate" class="space-y-5">
            <div>
                <label :class="labelClass">Operator</label>
                <SearchableSelect
                    v-model="rateForm.operator_id"
                    :options="operators"
                    all-label="All operators"
                    search-placeholder="Search operators..."
                />
                <p class="text-xs text-ink-500 mt-1">
                    An operator-specific rate wins over an "All operators" rate
                    for the same amount.
                </p>
                <InputError :message="rateForm.errors.operator_id" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass"
                        >Top-up amount from (£)</label
                    >
                    <input
                        v-model="rateForm.min_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                    />
                    <InputError :message="rateForm.errors.min_amount" />
                </div>
                <div>
                    <label :class="labelClass"
                        >Up to (£) – leave empty for no limit</label
                    >
                    <input
                        v-model="rateForm.max_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                    />
                    <InputError :message="rateForm.errors.max_amount" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">First top-up: Top up (£)</label>
                    <input
                        v-model="rateForm.first_topup_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                    />
                    <InputError :message="rateForm.errors.first_topup_amount" />
                </div>
                <div>
                    <label :class="labelClass">First top-up: Bonus (£)</label>
                    <input
                        v-model="rateForm.first_bonus_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                    />
                    <InputError :message="rateForm.errors.first_bonus_amount" />
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-ink-500"
                        >Ongoing top-ups (top-up number 2 onwards)</label
                    >
                    <button
                        type="button"
                        @click="addStep"
                        class="text-sm text-primary-light hover:text-primary"
                    >
                        + Add band
                    </button>
                </div>
                <div
                    v-for="(step, i) in rateForm.steps"
                    :key="i"
                    class="mb-2"
                >
                    <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-end">
                        <div>
                            <span class="text-[11px] text-ink-500">From #</span>
                            <input
                                v-model="step.from_sequence"
                                type="number"
                                min="2"
                                :class="inputClass"
                            />
                        </div>
                        <div>
                            <span class="text-[11px] text-ink-500"
                                >To # (empty = no end)</span
                            >
                            <input
                                v-model="step.to_sequence"
                                type="number"
                                min="2"
                                :class="inputClass"
                            />
                        </div>
                        <div>
                            <span class="text-[11px] text-ink-500"
                                >Pays each (£)</span
                            >
                            <input
                                v-model="step.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                :class="inputClass"
                            />
                        </div>
                        <button
                            type="button"
                            @click="removeStep(i)"
                            class="px-3 py-2 text-red-600 hover:text-red-500 text-sm"
                            aria-label="Remove band"
                        >
                            ✕
                        </button>
                    </div>
                    <InputError
                        :message="
                            rateForm.errors[`steps.${i}.from_sequence`] ||
                            rateForm.errors[`steps.${i}.to_sequence`] ||
                            rateForm.errors[`steps.${i}.amount`]
                        "
                    />
                </div>
                <p
                    v-if="!rateForm.steps.length"
                    class="text-xs text-ink-500"
                >
                    No ongoing bands: only the first top-up of a connection
                    pays.
                </p>
            </div>

            <div
                class="rounded-xl bg-surface-3 px-4 py-3 text-sm text-ink-700 flex flex-wrap gap-x-6 gap-y-1"
            >
                <span
                    >First top-up pays
                    <strong class="text-ink-900">{{
                        money(firstTotal(preview))
                    }}</strong></span
                >
                <span>
                    Lifetime per connection
                    <strong class="text-ink-900">{{
                        lifetimeTotal(preview) === null
                            ? "no limit"
                            : money(lifetimeTotal(preview))
                    }}</strong>
                </span>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input
                    v-model="rateForm.is_active"
                    type="checkbox"
                    class="rounded border-surface-3"
                />
                Active
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    @click="showRateModal = false"
                    class="px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="rateForm.processing"
                    class="px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60"
                >
                    Save rate
                </button>
            </div>
        </form>
    </CommissionModal>
</template>
