<script setup>
import { Head, useForm, Link } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });

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
});

function submit() {
    form.post("/admin/retailers", {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Add Retailer - MK Network" />
    <div>
        <div class="flex items-center gap-4 mb-8">
            <Link
                href="/admin/retailers"
                class="text-dark-400 hover:text-white transition"
            >
                <svg
                    class="w-6 h-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 19l-7-7 7-7"
                    />
                </svg>
            </Link>
            <div>
                <h1 class="text-3xl font-bold text-white">Add Retailer</h1>
                <p class="text-dark-300 mt-1">Create a new retailer account</p>
            </div>
        </div>

        <form
            @submit.prevent="submit"
            class="max-w-2xl mx-auto bg-dark-800 rounded-2xl p-6 border border-dark-600 space-y-6"
        >
            <!-- Personal Info -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">
                    Personal Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Full Name *</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-red-400 text-xs mt-1"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Email *</label
                        >
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.email"
                            class="text-red-400 text-xs mt-1"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Phone *</label
                        >
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.phone"
                            class="text-red-400 text-xs mt-1"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">Password</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Password *</label
                        >
                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.password"
                            class="text-red-400 text-xs mt-1"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Confirm Password *</label
                        >
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            required
                        />
                        <p
                            v-if="form.errors.password_confirmation"
                            class="text-red-400 text-xs mt-1"
                        >
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Shop Info -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">
                    Shop Information
                </h3>
                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Shop Name</label
                        >
                        <input
                            v-model="form.shop_name"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Address</label
                        >
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        ></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >City</label
                            >
                            <input
                                v-model="form.city"
                                type="text"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >County</label
                            >
                            <input
                                v-model="form.county"
                                type="text"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >Postcode</label
                            >
                            <input
                                v-model="form.postcode"
                                type="text"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-3 bg-primary text-white rounded-lg font-semibold hover:bg-primary-dark disabled:opacity-50 transition"
                >
                    {{ form.processing ? "Creating..." : "Create Retailer" }}
                </button>
                <Link
                    href="/admin/retailers"
                    class="px-6 py-3 border border-dark-600 text-dark-300 rounded-lg font-semibold hover:bg-dark-700 transition"
                >
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
