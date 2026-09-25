<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref } from "vue";
import { router } from "@inertiajs/vue3";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    numbers: Object,
    filters: Object,
});

const showAddForm = ref(false);
const showImportModal = ref(false);
const editingId = ref(null);

const form = ref({
    number: "",
    type: "mobile",
    operator_name: "",
    country: "",
    note: "",
    active: true,
});

const importForm = ref({
    numbers: "",
    type: "mobile",
    operator_name: "",
    country: "",
});

function openAdd() {
    editingId.value = null;
    form.value = {
        number: "",
        type: "mobile",
        operator_name: "",
        country: "",
        note: "",
        active: true,
    };
    showAddForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.value = {
        number: item.number,
        type: item.type,
        operator_name: item.operator_name || "",
        country: item.country || "",
        note: item.note || "",
        active: item.active,
    };
    showAddForm.value = true;
}

function submitForm() {
    if (editingId.value) {
        router.put(`/admin/allowed-numbers/${editingId.value}`, form.value, {
            onSuccess: () => {
                showAddForm.value = false;
            },
        });
    } else {
        router.post("/admin/allowed-numbers", form.value, {
            onSuccess: () => {
                showAddForm.value = false;
            },
        });
    }
}

function submitImport() {
    router.post("/admin/allowed-numbers/import", importForm.value, {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.value = { numbers: "", type: "mobile", operator_name: "", country: "" };
        },
    });
}

function toggleActive(item) {
    router.put(`/admin/allowed-numbers/${item.id}`, {
        ...item,
        active: !item.active,
    });
}

function deleteItem(id) {
    if (confirm("Are you sure?")) {
        router.delete(`/admin/allowed-numbers/${id}`);
    }
}

function filterBy(type) {
    if (props.filters.type === type) {
        router.visit("/admin/allowed-numbers", { data: { ...props.filters, type: null } });
    } else {
        router.visit("/admin/allowed-numbers", { data: { ...props.filters, type } });
    }
}
</script>

<template>
    <Head title="Allowed Numbers - Admin" />

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-white">Allowed Numbers</h1>
                    <p class="text-dark-300 text-sm mt-1">
                        Manage numbers/serials authorized for ReadReceipt PIN recharges
                    </p>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="showImportModal = true"
                        class="px-4 py-2 bg-dark-700 border border-dark-600 text-white rounded-xl text-sm font-medium hover:bg-dark-600 transition"
                    >
                        Import Bulk
                    </button>
                    <button
                        @click="openAdd"
                        class="px-4 py-2 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-dark transition"
                    >
                        Add Number
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-4 mb-6 flex items-center gap-3">
                <span class="text-xs text-dark-400 uppercase tracking-wider">Filter:</span>
                <button
                    @click="filterBy('mobile')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                        filters.type === 'mobile'
                            ? 'bg-primary text-white'
                            : 'bg-dark-700 text-dark-300 hover:text-white',
                    ]"
                >
                    Mobile
                </button>
                <button
                    @click="filterBy('serial')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                        filters.type === 'serial'
                            ? 'bg-primary text-white'
                            : 'bg-dark-700 text-dark-300 hover:text-white',
                    ]"
                >
                    Serial
                </button>
                <a
                    href="/admin/allowed-numbers"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-dark-700 text-dark-300 hover:text-white transition"
                >
                    All
                </a>
            </div>

            <!-- Table -->
            <div class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-dark-600">
                            <th class="text-left text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Number / Serial</th>
                            <th class="text-left text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Type</th>
                            <th class="text-left text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Operator</th>
                            <th class="text-left text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Country</th>
                            <th class="text-left text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Note</th>
                            <th class="text-center text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Status</th>
                            <th class="text-center text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Added</th>
                            <th class="text-right text-xs text-dark-400 uppercase tracking-wider px-6 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in numbers.data"
                            :key="item.id"
                            class="border-b border-dark-700 hover:bg-dark-700/50 transition"
                        >
                            <td class="px-6 py-3 font-mono text-sm text-white">{{ item.number }}</td>
                            <td class="px-6 py-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        item.type === 'mobile'
                                            ? 'bg-blue-500/20 text-blue-300'
                                            : 'bg-purple-500/20 text-purple-300',
                                    ]"
                                >
                                    {{ item.type === 'mobile' ? 'Mobile' : 'Serial' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-dark-300">{{ item.operator_name || '-' }}</td>
                            <td class="px-6 py-3 text-sm text-dark-300">{{ item.country || '-' }}</td>
                            <td class="px-6 py-3 text-xs text-dark-400">{{ item.note || '-' }}</td>
                            <td class="px-6 py-3 text-center">
                                <button
                                    @click="toggleActive(item)"
                                    :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium transition',
                                        item.active
                                            ? 'bg-green-500/20 text-green-300'
                                            : 'bg-red-500/20 text-red-300',
                                    ]"
                                >
                                    {{ item.active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-3 text-xs text-dark-400">
                                {{ new Date(item.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button
                                    @click="openEdit(item)"
                                    class="text-xs text-blue-300 hover:text-white transition mr-2"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteItem(item.id)"
                                    class="text-xs text-red-300 hover:text-white transition"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!numbers.data?.length">
                            <td colspan="8" class="text-center text-dark-400 py-8">
                                No numbers found. Add your first number or import bulk.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="numbers.links?.length > 3" class="mt-4 flex justify-center gap-1">
                <template v-for="link in numbers.links" :key="link.label">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 rounded-lg text-xs',
                            link.active
                                ? 'bg-primary text-white'
                                : 'bg-dark-700 text-dark-300 hover:text-white',
                        ]"
                    ></a>
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-xs text-dark-500"
                    ></span>
                </template>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div
            v-if="showAddForm"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
            @click.self="showAddForm = false"
        >
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 max-w-lg w-full">
                <h3 class="text-lg font-bold text-white mb-4">
                    {{ editingId ? 'Edit Number' : 'Add Number' }}
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Number / Serial</label>
                        <input
                            v-model="form.number"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="447700900123 or SERIAL-001"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Type</label>
                        <select
                            v-model="form.type"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                        >
                            <option value="mobile">Mobile Number</option>
                            <option value="serial">Serial Number</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Operator</label>
                        <input
                            v-model="form.operator_name"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="e.g. giffgaff"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Country</label>
                        <input
                            v-model="form.country"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="e.g. GB"
                            maxlength="10"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Note</label>
                        <input
                            v-model="form.note"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="Optional note"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            id="active"
                            class="rounded border-dark-600"
                        />
                        <label for="active" class="text-sm text-dark-300">Active</label>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button
                        @click="submitForm"
                        class="flex-1 py-2.5 bg-primary text-white rounded-xl font-medium hover:bg-primary-dark transition"
                    >
                        {{ editingId ? 'Update' : 'Add' }}
                    </button>
                    <button
                        @click="showAddForm = false"
                        class="flex-1 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div
            v-if="showImportModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
            @click.self="showImportModal = false"
        >
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 max-w-lg w-full">
                <h3 class="text-lg font-bold text-white mb-2">Bulk Import Numbers</h3>
                <p class="text-xs text-dark-400 mb-4">
                    Paste one number/serial per line. Duplicates will be skipped automatically.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Type</label>
                        <select
                            v-model="importForm.type"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                        >
                            <option value="mobile">Mobile Number</option>
                            <option value="serial">Serial Number</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Operator (optional)</label>
                        <input
                            v-model="importForm.operator_name"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="Applied to all imported numbers"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Country (optional)</label>
                        <input
                            v-model="importForm.country"
                            type="text"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm"
                            placeholder="e.g. GB"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 uppercase tracking-wider mb-1">Numbers (one per line)</label>
                        <textarea
                            v-model="importForm.numbers"
                            rows="8"
                            class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white text-sm font-mono"
                            placeholder="447700900123&#10;447700900124&#10;447700900125"
                        ></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button
                        @click="submitImport"
                        class="flex-1 py-2.5 bg-primary text-white rounded-xl font-medium hover:bg-primary-dark transition"
                    >
                        Import
                    </button>
                    <button
                        @click="showImportModal = false"
                        class="flex-1 py-2.5 bg-dark-700 text-white rounded-xl font-medium hover:bg-dark-600 transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
