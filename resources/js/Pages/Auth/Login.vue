<script setup>
import Checkbox from "@/Components/Checkbox.vue";
import MkLogo from "@/Components/MkLogo.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineOptions({ layout: GuestLayout });

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Login - MK Network" />

    <div class="w-full">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-white text-center mb-2">
            Welcome Back
        </h2>
        <p class="text-dark-300 text-center mb-8">
            Sign in to your retailer account
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

            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full input-dark"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <Checkbox
                        name="remember"
                        v-model:checked="form.remember"
                        class="rounded border-dark-600 bg-dark-700 text-primary"
                    />
                    <span class="ml-2 text-sm text-dark-300">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-primary-light hover:text-primary transition"
                >
                    Forgot your password?
                </Link>
            </div>

            <PrimaryButton
                class="w-full justify-center py-3"
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                {{ form.processing ? "Signing in..." : "Sign In" }}
            </PrimaryButton>
        </form>
    </div>
</template>
