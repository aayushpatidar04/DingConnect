<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { useToast } from "@/composables/useToast.js";

defineOptions({ layout: AppLayout });

const { success } = useToast();

const contactInfo = {
    phone: "+91 96915 65883",
    email: "support@mknetwork.com",
    address: "MK Network Communications",
    city: "India",
};

function submit() {
    success("Thank you for contacting us! We will get back to you soon.");
}
</script>

<template>
    <Head title="Contact Us - MK Network" />

    <div class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-bold text-white mb-4">
                    Contact Us
                </h1>
                <p class="text-xl text-dark-300 max-w-2xl mx-auto">
                    Have questions? Get in touch with our team and we will help
                    you get started.
                </p>
            </div>

            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600">
                    <h2 class="text-2xl font-bold text-white mb-6">
                        Send us a Message
                    </h2>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-dark-200 mb-2"
                                    >Full Name</label
                                >
                                <input
                                    type="text"
                                    class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                                    placeholder="Your name"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-dark-200 mb-2"
                                    >Email</label
                                >
                                <input
                                    type="email"
                                    class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                                    placeholder="your@email.com"
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >Phone</label
                            >
                            <input
                                type="tel"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                                placeholder="+91 99999 99999"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >Subject</label
                            >
                            <input
                                type="text"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                                placeholder="How can we help?"
                                required
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-dark-200 mb-2"
                                >Message</label
                            >
                            <textarea
                                rows="5"
                                class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark resize-none"
                                placeholder="Tell us more about your requirements..."
                                required
                            ></textarea>
                        </div>
                        <button
                            type="submit"
                            class="w-full py-3 bg-primary text-white rounded-lg font-semibold hover:bg-primary-dark transition"
                        >
                            Send Message
                        </button>
                    </form>
                </div>

                <!-- Contact Info -->
                <div class="space-y-6">
                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600"
                    >
                        <div class="flex items-start space-x-4">
                            <div
                                class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center flex-shrink-0"
                            >
                                <svg
                                    class="w-6 h-6 text-primary-light"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3
                                    class="text-lg font-semibold text-white mb-1"
                                >
                                    Phone
                                </h3>
                                <p class="text-dark-300">
                                    {{ contactInfo.phone }}
                                </p>
                                <p class="text-dark-400 text-sm mt-1">
                                    Mon - Sat, 9am - 7pm
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600"
                    >
                        <div class="flex items-start space-x-4">
                            <div
                                class="w-12 h-12 bg-accent/20 rounded-xl flex items-center justify-center flex-shrink-0"
                            >
                                <svg
                                    class="w-6 h-6 text-accent-light"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3
                                    class="text-lg font-semibold text-white mb-1"
                                >
                                    Email
                                </h3>
                                <a
                                    :href="'mailto:' + contactInfo.email"
                                    class="text-primary-light hover:text-primary transition"
                                    >{{ contactInfo.email }}</a
                                >
                                <p class="text-dark-400 text-sm mt-1">
                                    We reply within 24 hours
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-dark-800 rounded-2xl p-6 border border-dark-600"
                    >
                        <div class="flex items-start space-x-4">
                            <div
                                class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center flex-shrink-0"
                            >
                                <svg
                                    class="w-6 h-6 text-primary-light"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3
                                    class="text-lg font-semibold text-white mb-1"
                                >
                                    Office
                                </h3>
                                <p class="text-dark-300">
                                    {{ contactInfo.address }}
                                </p>
                                <p class="text-dark-400 text-sm mt-1">
                                    {{ contactInfo.city }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-primary/20 to-accent/20 rounded-2xl p-6 border border-primary/20"
                    >
                        <h3 class="text-lg font-semibold text-white mb-2">
                            Become a Retailer
                        </h3>
                        <p class="text-dark-300 text-sm mb-4">
                            Join 500+ retailers already using MK Network for
                            their recharge business.
                        </p>
                        <Link
                            href="/register"
                            class="btn-primary inline-block px-6 py-3 bg-primary text-white rounded-lg font-medium"
                            >Apply Now</Link
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
