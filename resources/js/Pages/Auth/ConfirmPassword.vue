import MkLogo from '@/Components/MkLogo.vue'
<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineOptions({ layout: GuestLayout });

const form = useForm({
    password: "",
});

const submit = () => {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Confirm Password - MK Network" />
    <div class="w-full">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-white text-center mb-4">
            Confirm Password
        </h2>
        <p class="text-dark-300 text-center mb-8">
            This is a secure area of the application. Please confirm your
            password before continuing.
        </p>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full input-dark"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <PrimaryButton
                class="w-full justify-center py-3"
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                Confirm Password
            </PrimaryButton>
        </form>

        <div class="mt-6 text-center">
            <Link
                href="/login"
                class="text-sm text-primary-light hover:text-primary transition"
            >
                Back to login
            </Link>
        </div>
    </div>
</template>
