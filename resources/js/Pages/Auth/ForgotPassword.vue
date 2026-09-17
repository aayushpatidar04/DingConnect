<script setup>
import MkLogo from "@/Components/MkLogo.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineOptions({ layout: GuestLayout });

defineProps({
    status: String,
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post(route("password.email"));
};
</script>

<template>
    <Head title="Forgot Password - MK Network" />

    <div class="w-full">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-white text-center mb-2">
            Forgot Password?
        </h2>
        <p class="text-dark-300 text-center mb-8">
            No problem. Just let us know your email address and we will email
            you a password reset link.
        </p>

        <div
            v-if="status"
            class="mb-4 p-3 bg-accent/20 border border-accent/30 rounded-lg text-sm text-accent-light text-center"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="email" value="Email Address" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full input-dark"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton
                class="w-full justify-center py-3"
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                Email Password Reset Link
            </PrimaryButton>
        </form>

        <div class="mt-6 text-center">
            <Link
                href="/login"
                class="text-sm text-primary-light hover:text-primary transition"
            >
                Remember your password? Sign in
            </Link>
        </div>
    </div>
</template>
