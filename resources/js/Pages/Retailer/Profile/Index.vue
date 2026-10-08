<script setup>
import InputError from "@/Components/InputError.vue";
import { Head, useForm } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({ user: Object });

const form = useForm({
    name: props.user.name ?? "",
    email: props.user.email ?? "",
    phone: props.user.phone ?? "",
    shop_name: props.user.shop_name ?? "",
    address: props.user.address ?? "",
    city: props.user.city ?? "",
    county: props.user.county ?? "",
    postcode: props.user.postcode ?? "",
    vat_number: props.user.vat_number ?? "",
    company_reg_number: props.user.company_reg_number ?? "",
    utr_number: props.user.utr_number ?? "",
    current_password: "",
    new_password: "",
    new_password_confirmation: "",
});

function submit() {
    form.put("/retailer/profile", {
        preserveScroll: true,
        onSuccess: () =>
            form.reset(
                "current_password",
                "new_password",
                "new_password_confirmation",
            ),
    });
}

const inputClass =
    "w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-3 text-ink-900 input-dark";
const labelClass = "block text-sm font-medium text-ink-700 mb-2";
</script>

<template>
    <Head title="My Profile - MK Network" />
    <FlashMessage />
    <div class="max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">My Profile</h1>
            <p class="text-ink-500">Manage your account details</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Personal -->
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 space-y-6"
            >
                <h2 class="text-lg font-semibold text-ink-900">Account</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label :class="labelClass">Full Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            :class="inputClass"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <label :class="labelClass">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            :class="inputClass"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                    <div>
                        <label :class="labelClass">Phone</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            :class="inputClass"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>
                    <div>
                        <label :class="labelClass">Shop Name</label>
                        <input
                            v-model="form.shop_name"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.shop_name"
                        />
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 space-y-6"
            >
                <h2 class="text-lg font-semibold text-ink-900">Address</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label :class="labelClass">Address</label>
                        <input
                            v-model="form.address"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.address"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">City</label>
                        <input
                            v-model="form.city"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.city" />
                    </div>
                    <div>
                        <label :class="labelClass">County</label>
                        <input
                            v-model="form.county"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.county"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Postcode</label>
                        <input
                            v-model="form.postcode"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.postcode"
                        />
                    </div>
                </div>
            </div>

            <!-- Business -->
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 space-y-6"
            >
                <h2 class="text-lg font-semibold text-ink-900">
                    Business Details
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label :class="labelClass">VAT Number</label>
                        <input
                            v-model="form.vat_number"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.vat_number"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Company Reg Number</label>
                        <input
                            v-model="form.company_reg_number"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.company_reg_number"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">UTR Number</label>
                        <input
                            v-model="form.utr_number"
                            type="text"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.utr_number"
                        />
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div
                class="bg-surface-2 rounded-2xl p-6 border border-surface-3 space-y-6"
            >
                <div>
                    <h2 class="text-lg font-semibold text-ink-900">
                        Change Password
                    </h2>
                    <p class="text-xs text-ink-500">
                        Leave blank to keep your current password.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label :class="labelClass">Current Password</label>
                        <input
                            v-model="form.current_password"
                            type="password"
                            autocomplete="current-password"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.current_password"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">New Password</label>
                        <input
                            v-model="form.new_password"
                            type="password"
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.new_password"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Confirm New Password</label>
                        <input
                            v-model="form.new_password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                    </div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 bg-primary text-ink-100 rounded-lg font-medium hover:bg-primary-dark disabled:opacity-60 transition"
            >
                {{ form.processing ? "Saving..." : "Update Profile" }}
            </button>
        </form>
    </div>
</template>