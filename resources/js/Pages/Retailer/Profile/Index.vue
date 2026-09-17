<script setup>
import InputError from "@/Components/InputError.vue";
import { Head, useForm } from "@inertiajs/vue3";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({ user: Object });

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    phone: props.user.phone,
    shop_name: props.user.shop_name || "",
    address: props.user.address || "",
    city: props.user.city || "",
    state: props.user.state || "",
    pincode: props.user.pincode || "",
});

function submit() {
    form.put("/retailer/profile");
}
</script>

<template>
    <Head title="My Profile - MK Network" />
    <div class="max-w-3xl space-y-6">
        <h1 class="text-3xl font-bold text-white mb-1">My Profile</h1>
        <p class="text-dark-300 mb-6">Manage your account details</p>

        <form
            @submit.prevent="submit"
            class="bg-dark-800 rounded-2xl p-6 border border-dark-600 space-y-6"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Full Name</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Email</label
                    >
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Phone</label
                    >
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Shop Name</label
                    >
                    <input
                        v-model="form.shop_name"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Address</label
                    >
                    <input
                        v-model="form.address"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >City</label
                    >
                    <input
                        v-model="form.city"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >State</label
                    >
                    <input
                        v-model="form.state"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-dark-200 mb-2"
                        >Pincode</label
                    >
                    <input
                        v-model="form.pincode"
                        type="text"
                        class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                    />
                </div>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-dark disabled:opacity-50 transition"
            >
                Update Profile
            </button>
        </form>
    </div>
</template>
