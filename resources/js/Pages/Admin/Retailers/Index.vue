<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({ retailers: Object });
</script>

<template>
    <Head title="Retailers - Admin" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white mb-1">Retailers</h1>
                <p class="text-dark-300">Manage registered retailers</p>
            </div>
            <div class="flex gap-2">
                <Link
                    href="/admin/retailers/export"
                    class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark text-sm font-medium transition"
                    >Export CSV</Link
                >
                <Link
                    href="/admin/retailers/create"
                    class="px-4 py-2 btn-primary text-white rounded-lg text-sm font-medium"
                    >Add Retailer</Link
                >
            </div>
        </div>

        <div
            class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-600">
                    <thead class="bg-dark-700">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                Retailer
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                Shop
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                Wallet
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                KYC
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                Status
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium text-dark-200 uppercase tracking-wider"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="retailer in retailers.data"
                            :key="retailer.id"
                            class="hover:bg-dark-700 transition"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">
                                    {{ retailer.name }}
                                </div>
                                <div class="text-sm text-dark-400">
                                    {{ retailer.email }}
                                </div>
                                <div class="text-sm text-dark-400">
                                    {{ retailer.phone }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-dark-200">
                                {{ retailer.shop_name || "-" }}
                            </td>
                            <td
                                class="px-6 py-4 text-sm font-medium text-white"
                            >
                                £
                                {{
                                    Number(
                                        retailer.wallet?.balance || 0,
                                    ).toFixed(2)
                                }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="{
                                        'bg-yellow-500/20 text-yellow-400':
                                            retailer.kyc_status === 'pending',
                                        'bg-accent/20 text-accent-light':
                                            retailer.kyc_status === 'approved',
                                        'bg-red-500/20 text-red-400':
                                            retailer.kyc_status === 'rejected',
                                    }"
                                    >{{ retailer.kyc_status }}</span
                                >
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="
                                        retailer.is_active
                                            ? 'bg-accent/20 text-accent-light'
                                            : 'bg-red-500/20 text-red-400'
                                    "
                                >
                                    {{
                                        retailer.is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <Link
                                    :href="`/admin/retailers/${retailer.id}`"
                                    class="text-primary-light hover:text-primary transition mr-2"
                                    >View</Link
                                >
                                <form
                                    :action="`/admin/retailers/${retailer.id}/approve`"
                                    method="POST"
                                    class="inline"
                                    v-if="retailer.kyc_status === 'pending'"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        :value="$page.props.csrf_token"
                                    />
                                    <button
                                        type="submit"
                                        class="text-accent-light hover:text-accent transition"
                                    >
                                        Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!retailers.data?.length"
                class="p-8 text-center text-dark-400"
            >
                No retailers found
            </div>
            <div
                v-if="retailers.links"
                class="mt-4 flex justify-center gap-2 p-4"
            >
                <template v-for="link in retailers.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm border border-dark-600 transition"
                        :class="
                            link.active
                                ? 'bg-primary text-white border-primary'
                                : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                        "
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-sm bg-dark-800 text-dark-500"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
