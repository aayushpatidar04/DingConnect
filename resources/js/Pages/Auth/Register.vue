<script setup>
import MkLogo from "@/Components/MkLogo.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineOptions({ layout: GuestLayout });
</script>

<template>
    <Head title="Register - MK Network" />

    <div class="w-full text-center py-8">
        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-white mb-4">
            Retailer Registration
        </h2>
        <p class="text-dark-300 mb-8 max-w-md mx-auto">
            Retailer accounts are created by administrators only. Please contact
            MK Network to request access to the platform.
        </p>
        <div class="space-y-4">
            <Link
                href="/login"
                class="inline-block btn-primary px-8 py-3 bg-primary text-white rounded-xl font-semibold"
            >
                Already have an account? Sign In
            </Link>
            <div class="block">
                <p class="text-dark-400 text-sm mt-4">
                    Need access? Contact us at
                </p>
                <p class="text-primary-light text-sm font-medium">
                    support@mknetwork.com
                </p>
            </div>
        </div>
    </div>
</template>
