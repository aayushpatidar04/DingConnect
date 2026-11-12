<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";
import MultiProviderDropdown from "@/Components/MultiProviderDropdown.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    comparisons: Object,
    operators: { type: Array, default: () => [] },
});

const uploadForm = useForm({
    operator_ids: props.operators.map((o) => o.id), // default select all
    reconciliation_month: new Date().toISOString().slice(0, 7), // default current YYYY-MM
    report_file: null,
});

const operatorOptions = computed(() =>
    props.operators.map((o) => ({ id: o.id, label: o.name }))
);

function submitReport() {
    uploadForm.post("/admin/sim-comparisons", {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset("report_file");
        },
    });
}
</script>

<template>
    <Head title="SIM Connection Reconciliation - Admin Panel" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-ink-900 mb-1">
                    SIM Connection Reconciliation & Provider File Comparison
                </h1>
                <p class="text-ink-500">
                    Select multiple network providers and reconciliation month, then upload the provider report to cross-reference recharges and identify missed connections.
                </p>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl p-6 shadow-sm">
            <h3 class="text-lg font-bold text-ink-900 mb-2">
                Upload Network Provider Report File
            </h3>
            <p class="text-xs text-ink-500 mb-4">
                The uploaded file must contain the following 4 mandatory column headers: <strong class="text-primary">Provider, Mobile, Serial, Country</strong>.
            </p>

            <form @submit.prevent="submitReport" class="space-y-4 max-w-xl">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Network Providers (Select One or More)
                        </label>
                        <MultiProviderDropdown
                            v-model="uploadForm.operator_ids"
                            :options="operatorOptions"
                            placeholder="Search providers..."
                            emptyText="No providers selected"
                        />
                        <InputError :message="uploadForm.errors.operator_ids" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                            Reconciliation Month
                        </label>
                        <input
                            v-model="uploadForm.reconciliation_month"
                            type="month"
                            required
                            class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary"
                        />
                        <InputError :message="uploadForm.errors.reconciliation_month" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-600 uppercase mb-1">
                        Select Report File (.xlsx, .xls, .csv)
                    </label>
                    <input
                        type="file"
                        accept=".xlsx, .xls, .csv, .txt"
                        @input="uploadForm.report_file = $event.target.files[0]"
                        class="w-full border border-surface-3 rounded-xl px-4 py-2.5 text-sm bg-surface-3 text-ink-900 focus:outline-none focus:border-primary file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-ink-100 hover:file:bg-primary-hover"
                        required
                    />
                    <InputError :message="uploadForm.errors.report_file" class="mt-1" />
                </div>

                <button
                    type="submit"
                    :disabled="uploadForm.processing"
                    class="px-6 py-2.5 bg-primary hover:bg-primary-hover text-ink-100 font-medium rounded-xl transition shadow-lg shadow-primary/20 disabled:opacity-50 text-sm"
                >
                    Run Automated Reconciliation
                </button>
            </form>
        </div>

        <!-- Previous Comparison Reports -->
        <div class="bg-surface-2 border border-surface-3 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-6 border-b border-surface-3">
                <h3 class="text-lg font-bold text-ink-900">
                    Previous Reconciliation Reports
                </h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    History of uploaded provider reports and comparison results
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-3 bg-surface-3/50 text-xs font-semibold text-ink-600 uppercase tracking-wider">
                            <th class="p-4">ID</th>
                            <th class="p-4">File Name</th>
                            <th class="p-4">Providers</th>
                            <th class="p-4">Month</th>
                            <th class="p-4">Internal</th>
                            <th class="p-4">Provider</th>
                            <th class="p-4">Matched</th>
                            <th class="p-4">Missing (Provider Missed)</th>
                            <th class="p-4">Additional</th>
                            <th class="p-4">Uploaded By</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3 text-sm text-ink-900">
                        <tr v-if="comparisons.data.length === 0">
                            <td colspan="11" class="text-center py-10 text-ink-500 font-medium">
                                No comparison reports uploaded yet.
                            </td>
                        </tr>
                        <tr
                            v-for="comp in comparisons.data"
                            :key="comp.id"
                            class="hover:bg-surface-3/30 transition"
                        >
                            <td class="p-4 font-semibold text-primary">
                                #{{ comp.id }}
                            </td>
                            <td class="p-4 font-medium">
                                {{ comp.file_name }}
                            </td>
                            <td class="p-4 font-medium">
                                {{ comp.provider_name }}
                            </td>
                            <td class="p-4 text-xs font-semibold">
                                {{ comp.reconciliation_month || '-' }}
                            </td>
                            <td class="p-4 font-bold">
                                {{ comp.total_internal }}
                            </td>
                            <td class="p-4 font-bold">
                                {{ comp.total_provider }}
                            </td>
                            <td class="p-4 text-emerald-600 font-bold">
                                {{ comp.matched_count }}
                            </td>
                            <td class="p-4 text-red-600 font-bold">
                                {{ comp.missing_count }}
                            </td>
                            <td class="p-4 text-amber-600 font-bold">
                                {{ comp.additional_count }}
                            </td>
                            <td class="p-4 text-xs text-ink-600">
                                {{ comp.admin?.name }}<br />
                                <span class="text-[10px] text-ink-400">{{ comp.created_at }}</span>
                            </td>
                            <td class="p-4 text-right">
                                <Link
                                    :href="`/admin/sim-comparisons/${comp.id}`"
                                    class="px-3 py-1.5 bg-primary/15 text-primary hover:bg-primary hover:text-ink-100 rounded-xl text-xs font-medium transition"
                                >
                                    View Report
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="comparisons.links && comparisons.links.length > 3"
                class="p-4 border-t border-surface-3 flex items-center justify-between"
            >
                <div class="text-xs text-ink-500">
                    Showing {{ comparisons.from }} to {{ comparisons.to }} of {{ comparisons.total }} results
                </div>
                <div class="flex space-x-1">
                    <template v-for="(link, i) in comparisons.links" :key="i">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 rounded-lg text-xs font-medium border border-surface-3 transition"
                            :class="[
                                link.active
                                    ? 'bg-primary text-ink-100 border-primary'
                                    : 'text-ink-700 hover:bg-surface-3',
                                !link.url ? 'opacity-40 cursor-not-allowed' : '',
                            ]"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
