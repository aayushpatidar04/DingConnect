<script setup>
import { ref } from "vue";
import MkLogo from "@/Components/MkLogo.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: GuestLayout });

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    otpPending: {
        type: Boolean,
        default: false,
    },
});

const otpStep = ref(props.otpPending);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const otpForm = useForm({
    otp: "",
});

const submit = () => {
    form.post(route("login"), {
        onSuccess: () => {
            otpStep.value = true;
            otpForm.reset();
        },
        onFinish: () => form.reset("password"),
    });
};

const verifyOtp = () => {
    otpForm.post(route("login.otp.verify"), {
        onSuccess: () => otpForm.reset(),
    });
};

const resendOtp = () => {
    otpForm.clearErrors();

    otpForm.post(route("login.otp.resend"), {
        onSuccess: () => otpForm.reset(),
    });
};
</script>

<template>
    <Head title="Login - MK Network" />
    <FlashMessage />

    <div class="w-full">
        <div class="flex justify-center mb-8">
            <Link href="/">
                <MkLogo size="xl" />
            </Link>
        </div>

        <h2 class="text-3xl font-bold text-ink-900 text-center mb-2">
            {{ otpStep ? "Verify Your Email" : "Welcome Back" }}
        </h2>

        <p class="text-ink-500 text-center mb-8">
            {{
                otpStep
                    ? "Enter the six-digit verification code sent to your email."
                    : "Sign in to your account"
            }}
        </p>

        <div
            v-if="status"
            class="mb-4 p-3 bg-accent/20 border border-accent/30 rounded-lg text-sm text-accent-light text-center"
        >
            {{ status }}
        </div>

        <!-- Password Login -->
        <form
            v-if="!otpStep"
            @submit.prevent="submit"
            class="space-y-6"
        >
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

            <div class="flex justify-end">
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

        <!-- OTP Verification -->
        <div v-else class="space-y-6">
            <form @submit.prevent="verifyOtp" class="space-y-6">
                <div>
                    <InputLabel for="otp" value="Email Verification Code" />

                    <TextInput
                        id="otp"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        class="mt-1 block w-full input-dark text-center tracking-[0.5em]"
                        v-model="otpForm.otp"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="000000"
                    />

                    <InputError class="mt-2" :message="otpForm.errors.otp" />
                </div>

                <PrimaryButton
                    class="w-full justify-center py-3"
                    :class="{ 'opacity-50': otpForm.processing }"
                    :disabled="otpForm.processing"
                >
                    {{
                        otpForm.processing
                            ? "Verifying..."
                            : "Verify & Sign In"
                    }}
                </PrimaryButton>
            </form>

            <form @submit.prevent="resendOtp" class="text-center">
                <button
                    type="submit"
                    class="text-sm text-primary-light hover:text-primary transition disabled:opacity-50"
                    :disabled="otpForm.processing"
                >
                    Resend OTP
                </button>
            </form>
        </div>
    </div>
</template>