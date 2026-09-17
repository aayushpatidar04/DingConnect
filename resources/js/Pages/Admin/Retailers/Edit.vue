<script setup>
import { Head, useForm, Link } from "@inertiajs/vue3";
import { watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({ retailer: Object });

const form = useForm({
    name: "",
    email: "",
    phone: "",
    shop_name: "",
    address: "",
    city: "",
    county: "",
    postcode: "",
    vat_number: "",
    company_reg_number: "",
    utr_number: "",
    is_active: true,
});

watch(
    () => props.retailer,
    (retailer) => {
        if (!retailer) return;
        form.name = retailer.name || "";
        form.email = retailer.email || "";
        form.phone = retailer.phone || "";
        form.shop_name = retailer.shop_name || "";
        form.address = retailer.address || "";
        form.city = retailer.city || "";
        form.county = retailer.county || "";
        form.postcode = retailer.postcode || "";
        form.vat_number = retailer.vat_number || "";
        form.company_reg_number = retailer.company_reg_number || "";
        form.utr_number = retailer.utr_number || "";
        form.is_active = retailer.is_active ?? true;
    },
    { immediate: true },
);

function submit() {
    form.put(`/admin/retailers/${props.retailer.id}`, {
        onSuccess: () => {},
    });
}
</script>

<template>
    <Head :title="`Edit ${retailer?.name || 'Retailer'}`" />
    <div>
        <div class="flex items-center justify-between gap-4 mb-8">
            <Link
                :href="`/admin/retailers/${retailer.id}`"
                class="text-dark-400 hover:text-white transition"
                >← Back to Retailer</Link
            >
            <h1 class="text-3xl font-bold text-white">Edit Retailer</h1>
        </div>

        <form
            @submit.prevent="submit"
            class="max-w-2xl mx-auto bg-dark-800 rounded-2xl p-6 border border-dark-600 space-y-6"
        >
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
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Status</label
                        >
                        <select
                            v-model="form.is_active"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                        >
                            <option :value="true">Active</option>
                            <option :value="false">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-white mb-4">
                    Shop / Business Information
                </h3>
                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Shop / Business Name</label
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
                                placeholder="e.g. Greater London"
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
                                placeholder="e.g. SW1A 1AA"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-white mb-4">
                    UK Business Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >VAT Number</label
                        >
                        <input
                            v-model="form.vat_number"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            placeholder="GB123456789"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Company Registration Number</label
                        >
                        <input
                            v-model="form.company_reg_number"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            placeholder="e.g. 12345678"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-dark-200 mb-2"
                            >Unique Taxpayer Reference (UTR)</label
                        >
                        <input
                            v-model="form.utr_number"
                            type="text"
                            class="w-full border border-dark-600 rounded-lg px-4 py-3 bg-dark-700 text-white input-dark"
                            placeholder="12 digits"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-dark-600">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-3 bg-primary text-white rounded-lg font-semibold hover:bg-primary-dark disabled:opacity-50 transition"
                >
                    {{ form.processing ? "Saving..." : "Save Changes" }}
                </button>
                <Link
                    :href="`/admin/retailers/${retailer.id}`"
                    class="px-6 py-3 border border-dark-600 text-dark-300 rounded-lg font-semibold hover:bg-dark-700 transition"
                    >Cancel</Link
                >
            </div>
        </form>
    </div>
</template>
