import MkLogo from '@/Components/MkLogo.vue'
<template>
    <div class="w-full">
        <div class="flex justify-center mb-8">
            <a href="/">
                <MkLogo size="xl" />
            </a>
        </div>

        <h2 class="text-3xl font-bold text-white text-center mb-4">
            Verify Your Email
        </h2>

        <div class="mb-6 text-sm text-dark-300 text-center">
            Thanks for signing up! Before getting started, could you verify your
            email address by clicking on the link we just emailed to you? If you
            didn't receive the email, we will gladly send you another.
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 p-3 bg-accent/20 border border-accent/30 rounded-lg text-sm text-accent-light text-center"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <PrimaryButton
                class="w-full justify-center py-3"
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                Resend Verification Email
            </PrimaryButton>
        </form>

        <div class="mt-6 text-center">
            <Link
                href="/retailer"
                class="text-sm text-primary-light hover:text-primary transition"
            >
                Back to dashboard
            </Link>
        </div>
    </div>
</template>

<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

defineOptions({ layout: GuestLayout });

defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route("verification.send"));
};

const verificationLinkSent = computed(
    () => props.status === "verification-link-sent",
);
</script>
