<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import MkLogo from "@/Components/MkLogo.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineOptions({ layout: GuestLayout });

const form = useForm({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    shop_name: "",
    address: "",
    city: "",
    county: "",
    postcode: "",
    vat_number: "",
    company_reg_number: "",
    utr_number: "",
});

function submit() {
    form.post("/register", {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
}
</script>

<template>
    <Head title="Register as Retailer - MK Network" />

    <div class="mx-auto py-10">
        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-ink-900 text-center mb-2">
            Retailer Registration
        </h2>
        <p class="text-ink-500 text-center mb-8">
            Create an account to start recharging with MK Network
        </p>

        <!-- Pending notice -->
        <div
            v-if="$page.props.status"
            class="mb-6 p-4 bg-accent/20 border border-accent/30 rounded-xl text-sm text-accent-dark"
        >
            {{ $page.props.status }}
        </div>

        <form
            @submit.prevent="submit"
            class="bg-surface-2 rounded-2xl p-6 border border-surface-3 space-y-6"
        >
            <!-- Personal Information -->
            <div>
                <h3 class="text-lg font-semibold text-ink-900 mb-4">
                    Personal Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Full Name *</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Email *</label
                        >
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.email"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Phone *</label
                        >
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.phone"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div>
                <h3 class="text-lg font-semibold text-ink-900 mb-4">
                    Password
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Password *</label
                        >
                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.password"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Confirm Password *</label
                        >
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.password_confirmation"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Shop Information -->
            <div>
                <h3 class="text-lg font-semibold text-ink-900 mb-4">
                    Shop Information
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Shop Name</label
                        >
                        <input
                            v-model="form.shop_name"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                        />
                        <p
                            v-if="form.errors.shop_name"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.shop_name }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Address</label
                        >
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                        ></textarea>
                        <p
                            v-if="form.errors.address"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.address }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-2"
                                >City</label
                            >
                            <input
                                v-model="form.city"
                                type="text"
                                class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            />
                            <p
                                v-if="form.errors.city"
                                class="text-red-600 text-xs mt-1"
                            >
                                {{ form.errors.city }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-2"
                                >County</label
                            >
                            <input
                                v-model="form.county"
                                type="text"
                                class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            />
                            <p
                                v-if="form.errors.county"
                                class="text-red-600 text-xs mt-1"
                            >
                                {{ form.errors.county }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-2"
                                >Postcode</label
                            >
                            <input
                                v-model="form.postcode"
                                type="text"
                                class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            />
                            <p
                                v-if="form.errors.postcode"
                                class="text-red-600 text-xs mt-1"
                            >
                                {{ form.errors.postcode }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UK Business Details -->
            <div>
                <h3 class="text-lg font-semibold text-ink-900 mb-4">
                    UK Business Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >VAT Number (optional)</label
                        >
                        <input
                            v-model="form.vat_number"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            placeholder="GB123456789"
                        />
                        <p
                            v-if="form.errors.vat_number"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.vat_number }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Company Registration Number (optional)</label
                        >
                        <input
                            v-model="form.company_reg_number"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            placeholder="e.g. 12345678"
                        />
                        <p
                            v-if="form.errors.company_reg_number"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.company_reg_number }}
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-ink-700 mb-2"
                            >Unique Taxpayer Reference (UTR) (optional)</label
                        >
                        <input
                            v-model="form.utr_number"
                            type="text"
                            class="w-full border border-surface-3 rounded-lg px-4 py-3 bg-surface-0 text-ink-900 input-dark"
                            placeholder="12 digits"
                        />
                        <p
                            v-if="form.errors.utr_number"
                            class="text-red-600 text-xs mt-1"
                        >
                            {{ form.errors.utr_number }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- KYC Notice -->
            <div
                class="bg-accent/10 border border-accent/30 rounded-xl p-4 text-sm text-ink-700"
            >
                <p class="font-semibold text-ink-900 mb-1">
                    KYC Verification Required
                </p>
                <p>
                    After registration, your account will be in <strong>pending</strong> status.
                    Our team will review your details and verify your identity before
                    you can access the platform. This usually takes 1-2 business days.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full sm:w-auto px-8 py-3 btn-primary text-ink-100 rounded-xl font-semibold disabled:opacity-60 transition"
                >
                    {{
                        form.processing
                            ? "Creating Account..."
                            : "Register Account"
                    }}
                </button>
                <Link
                    href="/login"
                    class="text-ink-500 px-8 py-3 bg-gray-200 hover:text-ink-900 rounded-xl text-sm transition"
                >
                    Already have an account? Sign In
                </Link>
            </div>
        </form>
    </div>
</template>
