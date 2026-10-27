<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    plans: { type: Array, default: () => [] },
    operators: { type: Array, default: () => [] },
});

const search = ref("");
const showModal = ref(false);
const visiblePlans = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.plans;

    return props.plans.filter((plan) =>
        [plan.operator, plan.country_iso, plan.currency, ...plan.benefits]
            .filter(Boolean)
            .join(" ")
            .toLowerCase()
            .includes(term),
    );
});

const form = useForm({
    id: null,
    operator_id: "",
    price: "",
    currency: "GBP",
    benefitsText: "",
    is_active: true,
});

function open(plan = null) {
    form.clearErrors();
    form.id = plan?.id ?? null;
    form.operator_id = plan?.operator_id ?? "";
    form.price = plan?.price ?? "";
    form.currency = plan?.currency ?? "GBP";
    form.benefitsText = plan?.benefits?.join("\n") ?? "";
    form.is_active = plan?.is_active ?? true;
    showModal.value = true;
}

function submit() {
    form.clearErrors();
    const benefits = form.benefitsText
        .split(/\r?\n/)
        .map((benefit) => benefit.trim())
        .filter(Boolean);
    form.transform((data) => ({
        operator_id: data.operator_id,
        price: data.price,
        currency: data.currency.trim().toUpperCase(),
        benefits,
        is_active: data.is_active,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };

    if (form.id) {
        form.put(`/admin/network-plans/${form.id}`, options);
    } else {
        form.post("/admin/network-plans", options);
    }
}

function remove(plan) {
    if (!confirm(`Delete the ${plan.currency} ${plan.price.toFixed(2)} ${plan.operator} plan?`)) {
        return;
    }

    router.delete(`/admin/network-plans/${plan.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Network Plans - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-ink-900">Network Plans</h1>
                <p class="mt-1 max-w-3xl text-sm text-ink-500">
                    Manage plan benefits by network, price, and currency. One
                    entry is shared by direct top-up and PIN products at the
                    same price.
                </p>
            </div>
            <button
                type="button"
                @click="open()"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-ink-100 transition hover:bg-primary-dark"
            >
                + Add plan
            </button>
        </div>

        <div class="rounded-2xl border border-surface-3 bg-surface-2 p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-500">
                    {{ plans.length }} plan{{ plans.length === 1 ? "" : "s" }}
                </p>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search networks or benefits..."
                    class="w-full rounded-lg border border-surface-3 bg-surface-0 px-3 py-2 text-sm text-ink-900 outline-none focus:border-primary sm:max-w-xs"
                />
            </div>

            <div v-if="visiblePlans.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-500">
                            <th class="px-4 py-3">Network</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="px-4 py-3">Benefits</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="plan in visiblePlans" :key="plan.id">
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-ink-900">
                                {{ plan.operator }}
                                <span v-if="plan.country_iso" class="text-ink-500">
                                    ({{ plan.country_iso }})
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-ink-700">
                                {{ plan.currency }} {{ plan.price.toFixed(2) }}
                            </td>
                            <td class="min-w-64 px-4 py-4 text-ink-700">
                                <ul class="list-disc space-y-1 pl-5">
                                    <li v-for="benefit in plan.benefits" :key="benefit">
                                        {{ benefit }}
                                    </li>
                                </ul>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    :class="plan.is_active ? 'bg-green-500/10 text-green-700' : 'bg-surface-3 text-ink-500'"
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ plan.is_active ? "Published" : "Hidden" }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <button
                                    type="button"
                                    @click="open(plan)"
                                    class="font-medium text-primary hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="remove(plan)"
                                    class="ml-4 font-medium text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rounded-xl border border-dashed border-surface-3 py-12 text-center">
                <p class="font-medium text-ink-900">No network plans found</p>
                <p class="mt-1 text-sm text-ink-500">
                    Add a plan to publish provider benefits to retailers and the website.
                </p>
            </div>
        </div>
    </div>

    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="showModal = false"
    >
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-surface-3 bg-surface-0 p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-ink-900">
                        {{ form.id ? "Edit network plan" : "Add network plan" }}
                    </h2>
                    <p class="mt-1 text-sm text-ink-500">
                        Benefits are shared for PIN and direct top-up at this price.
                    </p>
                </div>
                <button
                    type="button"
                    @click="showModal = false"
                    class="text-2xl leading-none text-ink-500 hover:text-ink-900"
                    aria-label="Close"
                >
                    &times;
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink-700">
                        Network
                    </label>
                    <select
                        v-model="form.operator_id"
                        required
                        class="w-full rounded-lg border border-surface-3 bg-surface-0 px-3 py-2 text-sm text-ink-900"
                    >
                        <option value="" disabled>Select a network</option>
                        <option
                            v-for="operator in operators"
                            :key="operator.id"
                            :value="operator.id"
                        >
                            {{ operator.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.operator_id" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink-700">
                            Plan price
                        </label>
                        <input
                            v-model="form.price"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            class="w-full rounded-lg border border-surface-3 bg-surface-0 px-3 py-2 text-sm text-ink-900"
                        />
                        <InputError :message="form.errors.price" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink-700">
                            Currency
                        </label>
                        <input
                            v-model="form.currency"
                            type="text"
                            minlength="3"
                            maxlength="3"
                            required
                            @input="form.currency = form.currency.toUpperCase()"
                            class="w-full rounded-lg border border-surface-3 bg-surface-0 px-3 py-2 text-sm uppercase text-ink-900"
                        />
                        <InputError :message="form.errors.currency" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink-700">
                        Benefits
                    </label>
                    <textarea
                        v-model="form.benefitsText"
                        rows="5"
                        required
                        placeholder="40 GB data&#10;Unlimited minutes and texts"
                        class="w-full rounded-lg border border-surface-3 bg-surface-0 px-3 py-2 text-sm text-ink-900"
                    ></textarea>
                    <p class="mt-1 text-xs text-ink-500">
                        Enter one benefit per line.
                    </p>
                    <InputError :message="form.errors.benefits" class="mt-1" />
                    <InputError :message="form.errors['benefits.0']" class="mt-1" />
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-surface-3 text-primary focus:ring-primary"
                    />
                    Publish this plan to retailers and the website
                </label>

                <div class="flex justify-end gap-3 border-t border-surface-3 pt-4">
                    <button
                        type="button"
                        @click="showModal = false"
                        class="rounded-lg border border-surface-3 px-4 py-2 text-sm text-ink-700 hover:bg-surface-2"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-ink-100 hover:bg-primary-dark disabled:opacity-60"
                    >
                        {{ form.processing ? "Saving..." : "Save plan" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
