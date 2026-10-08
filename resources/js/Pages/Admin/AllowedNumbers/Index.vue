<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    folders: { type: Array, default: null },
    manualCount: { type: Number, default: 0 },
    numbers: { type: Object, default: null },
    currentFolder: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

/* ---------- Search / filters ---------- */
const search = ref(props.filters.search ?? "");
const inactive = ref(["1", "true", true].includes(props.filters.inactive));

function visit() {
    const params = {
        folder: props.currentFolder?.id,
        search: search.value,
        inactive: inactive.value ? 1 : null,
    };
    const clean = Object.fromEntries(
        Object.entries(params).filter(
            ([, v]) => v !== null && v !== undefined && v !== "",
        ),
    );
    router.get("/admin/allowed-numbers", clean, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let timer = null;
watch(search, (v) => {
    if (v === (props.filters.search ?? "")) return;
    clearTimeout(timer);
    timer = setTimeout(visit, 400);
});
watch(inactive, visit);
watch(
    () => props.filters.search,
    (v) => (search.value = v ?? ""),
);

/* ---------- Add / edit modal ---------- */
const showModal = ref(false);
const tab = ref("file"); // "file" | "manual"
const editingId = ref(null);
const fileInput = ref(null);

const manualForm = useForm({
    mobile: "",
    serial: "",
    provider: "",
    country: "",
    note: "",
    active: true,
});
const fileForm = useForm({ file: null });

function openAdd() {
    editingId.value = null;
    manualForm.reset();
    manualForm.clearErrors();
    fileForm.reset();
    fileForm.clearErrors();
    tab.value = "file";
    showModal.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    manualForm.clearErrors();
    manualForm.mobile = item.mobile === "0" ? "" : item.mobile;
    manualForm.serial = item.serial === "0" ? "" : item.serial;
    manualForm.provider = item.provider || "";
    manualForm.country = item.country || "";
    manualForm.note = item.note || "";
    manualForm.active = item.active;
    tab.value = "manual";
    showModal.value = true;
}

function submitManual() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };
    if (editingId.value) {
        manualForm.put(`/admin/allowed-numbers/${editingId.value}`, opts);
    } else {
        manualForm.post("/admin/allowed-numbers", opts);
    }
}

function onFileChange(e) {
    fileForm.file = e.target.files[0] || null;
}

function submitFile() {
    if (!fileForm.file) return;
    fileForm.post("/admin/allowed-numbers/import-file", {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            fileForm.reset();
            if (fileInput.value) fileInput.value.value = "";
        },
    });
}

/* ---------- Row / folder actions ---------- */
function toggleActive(item) {
    router.put(
        `/admin/allowed-numbers/${item.id}`,
        {
            mobile: item.mobile,
            serial: item.serial,
            provider: item.provider,
            country: item.country,
            note: item.note,
            active: !item.active,
        },
        { preserveScroll: true },
    );
}

function deleteItem(id) {
    if (confirm("Are you sure?")) {
        router.delete(`/admin/allowed-numbers/${id}`, { preserveScroll: true });
    }
}

function deleteFolder(id, name, count) {
    if (
        confirm(
            `Delete "${name}" and its ${count} number(s)? This cannot be undone.`,
        )
    ) {
        router.delete(`/admin/allowed-numbers/files/${id}`);
    }
}

/* ---------- Import results ---------- */
const page = usePage();
const importResults = computed(() => page.props.flash?.import_results ?? []);
const dismissedResults = ref(false);
watch(importResults, () => (dismissedResults.value = false));

const reasonLabel = {
    invalid: "Both mobile and serial are missing or invalid",
    duplicate_in_file: "Repeated in this file",
    duplicate_existing: "Already exists",
};

const formatDate = (d) =>
    d
        ? new Date(d).toLocaleDateString("en-GB", {
              timeZone: "Europe/London",
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";

const inputClass =
    "w-full bg-surface-3 border border-surface-3 rounded-xl px-4 py-2 text-ink-900 text-sm";
const labelClass = "block text-xs text-ink-500 uppercase tracking-wider mb-1";
</script>

<template>
    <Head title="Allowed Numbers - Admin" />
    <FlashMessage />

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-ink-900">
                        Allowed Numbers
                    </h1>
                    <p class="text-ink-500 text-sm mt-1">
                        Mobile and serial pairs authorized for ReadReceipt PIN
                        recharges
                    </p>
                </div>
                <button
                    @click="openAdd"
                    class="px-4 py-2 bg-primary text-ink-100 rounded-xl text-sm font-medium hover:bg-primary-dark transition"
                >
                    Add Number
                </button>
            </div>

            <!-- Import result details -->
            <div
                v-if="importResults.length && !dismissedResults"
                class="bg-yellow-500/10 border border-yellow-500/30 rounded-2xl p-4"
            >
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-yellow-600">
                        Skipped rows ({{ importResults.length
                        }}{{ importResults.length === 200 ? "+" : "" }})
                    </h3>
                    <button
                        class="text-xs text-ink-500 hover:text-ink-900"
                        @click="dismissedResults = true"
                    >
                        Dismiss
                    </button>
                </div>
                <div
                    class="max-h-48 overflow-y-auto text-xs text-ink-700 space-y-1"
                >
                    <div
                        v-for="(r, i) in importResults"
                        :key="i"
                        class="flex gap-3"
                    >
                        <span class="text-ink-500 w-14 shrink-0">{{
                            r.line ? "Line " + r.line : ""
                        }}</span>
                        <span class="font-mono"
                            >{{ r.mobile || "-" }} / {{ r.serial || "-" }}</span
                        >
                        <span class="ml-auto text-yellow-600">{{
                            reasonLabel[r.reason] || r.reason
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div
                class="bg-surface-2 rounded-2xl border border-surface-3 p-4 flex flex-wrap items-center gap-3"
            >
                <nav
                    v-if="currentFolder"
                    class="text-sm flex items-center gap-2 mr-2"
                >
                    <Link
                        href="/admin/allowed-numbers"
                        class="text-primary-light hover:text-primary"
                    >
                        All files
                    </Link>
                    <span class="text-ink-500">/</span>
                    <span class="text-ink-900 font-medium">{{
                        currentFolder.name
                    }}</span>
                </nav>

                <input
                    v-model="search"
                    type="search"
                    placeholder="Search mobile, serial or provider"
                    class="border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 min-w-[240px]"
                />
                <label
                    v-if="numbers"
                    class="flex items-center gap-2 text-sm text-ink-500"
                >
                    <input
                        v-model="inactive"
                        type="checkbox"
                        class="rounded border-surface-3"
                    />
                    Inactive only
                </label>

                <div
                    v-if="currentFolder && !currentFolder.is_manual"
                    class="ml-auto flex gap-2"
                >
                    <a
                        :href="`/admin/allowed-numbers/files/${currentFolder.id}/download`"
                        class="px-3 py-2 border border-surface-3 rounded-lg text-xs text-ink-500 hover:bg-surface-3 transition"
                    >
                        Download original file
                    </a>
                    <button
                        @click="
                            deleteFolder(
                                currentFolder.id,
                                currentFolder.name,
                                numbers?.total ?? 0,
                            )
                        "
                        class="px-3 py-2 border border-red-500/40 rounded-lg text-xs text-red-600 hover:bg-red-500/10 transition"
                    >
                        Delete folder
                    </button>
                </div>
            </div>

            <!-- ============ FOLDERS VIEW ============ -->
            <div v-if="folders">
                <div
                    v-if="!folders.length && !manualCount"
                    class="bg-surface-2 rounded-2xl border border-surface-3 p-10 text-center text-ink-500"
                >
                    No files yet. Click "Add Number" to upload a CSV or add a
                    number manually.
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                    <!-- Manual entries -->
                    <div
                        v-if="manualCount"
                        class="bg-surface-2 border border-surface-3 hover:border-primary rounded-2xl p-5 transition"
                    >
                        <Link
                            href="/admin/allowed-numbers?folder=manual"
                            class="flex items-center gap-3"
                        >
                            <span class="text-3xl">✍️</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-ink-900">
                                    Manual entries
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ manualCount }} number{{
                                        manualCount === 1 ? "" : "s"
                                    }}
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- File folders -->
                    <div
                        v-for="f in folders"
                        :key="f.id"
                        class="bg-surface-2 border border-surface-3 hover:border-primary rounded-2xl p-5 transition"
                    >
                        <Link
                            :href="`/admin/allowed-numbers?folder=${f.id}`"
                            class="flex items-center gap-3"
                        >
                            <span class="text-3xl">📁</span>
                            <div class="min-w-0">
                                <div
                                    class="font-semibold text-ink-900 truncate"
                                    :title="f.name"
                                >
                                    {{ f.name }}
                                </div>
                                <div class="text-xs text-ink-500">
                                    {{ f.numbers_count }} number{{
                                        f.numbers_count === 1 ? "" : "s"
                                    }}
                                    ·
                                    {{ formatDate(f.created_at) }}
                                </div>
                            </div>
                        </Link>
                        <div
                            class="mt-3 flex items-center justify-between text-xs"
                        >
                            <span class="text-ink-500">
                                {{ f.imported_rows }} imported ·
                                {{ f.skipped_rows }} skipped
                            </span>
                            <span class="flex gap-3">
                                <a
                                    :href="`/admin/allowed-numbers/files/${f.id}/download`"
                                    class="text-blue-300 hover:text-ink-900 transition"
                                    >Download</a
                                >
                                <button
                                    @click="
                                        deleteFolder(
                                            f.id,
                                            f.name,
                                            f.numbers_count,
                                        )
                                    "
                                    class="text-red-600 hover:text-ink-900 transition"
                                >
                                    Delete
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ NUMBERS VIEW ============ -->
            <div
                v-else-if="numbers"
                class="bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-surface-3">
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Mobile
                                </th>
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Serial
                                </th>
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Provider
                                </th>
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Country
                                </th>
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Note
                                </th>
                                <th
                                    v-if="!currentFolder"
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    File
                                </th>
                                <th
                                    class="text-center text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Status
                                </th>
                                <th
                                    class="text-left text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Added
                                </th>
                                <th
                                    class="text-right text-xs text-ink-500 uppercase tracking-wider px-6 py-4"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in numbers.data"
                                :key="item.id"
                                class="border-b border-surface-3 hover:bg-surface-2/70 transition"
                            >
                                <td class="px-6 py-3 font-mono text-sm text-ink-900">{{ item.mobile === "0" ? "-" : item.mobile }}</td>
                                <td class="px-6 py-3 font-mono text-sm text-ink-900">{{ item.serial === "0" ? "-" : item.serial }}</td>
                                <td class="px-6 py-3 text-sm text-ink-500">
                                    {{ item.provider || "-" }}
                                </td>
                                <td class="px-6 py-3 text-sm text-ink-500">
                                    {{ item.country || "-" }}
                                </td>
                                <td class="px-6 py-3 text-xs text-ink-500">
                                    {{ item.note || "-" }}
                                </td>
                                <td
                                    v-if="!currentFolder"
                                    class="px-6 py-3 text-xs text-ink-500"
                                >
                                    {{ item.file_name || "Manual" }}
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <button
                                        @click="toggleActive(item)"
                                        :class="[
                                            'px-2 py-0.5 rounded text-xs font-medium transition',
                                            item.active
                                                ? 'bg-green-200 text-green-600'
                                                : 'bg-red-500/20 text-red-600',
                                        ]"
                                    >
                                        {{
                                            item.active ? "Active" : "Inactive"
                                        }}
                                    </button>
                                </td>
                                <td class="px-6 py-3 text-xs text-ink-500">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <button
                                        @click="openEdit(item)"
                                        class="text-xs text-blue-300 hover:text-ink-900 transition mr-2"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteItem(item.id)"
                                        class="text-xs text-red-600 hover:text-ink-900 transition"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!numbers.data?.length">
                                <td
                                    :colspan="currentFolder ? 8 : 9"
                                    class="text-center text-ink-500 py-8"
                                >
                                    No numbers found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="numbers.total > 0"
                    class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-surface-3"
                >
                    <p class="text-xs text-ink-500">
                        Showing {{ numbers.from }}–{{ numbers.to }} of
                        {{ numbers.total }}
                    </p>
                    <div
                        v-if="numbers.last_page > 1"
                        class="flex flex-wrap justify-center gap-1"
                    >
                        <template
                            v-for="link in numbers.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1 rounded-lg text-xs',
                                    link.active
                                        ? 'bg-primary text-ink-100'
                                        : 'bg-surface-3 text-ink-500 hover:text-ink-900',
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 rounded-lg text-xs text-ink-500 opacity-50"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ ADD / EDIT MODAL ============ -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 overflow-y-auto"
            @click.self="showModal = false"
        >
            <div
                class="bg-surface-2 rounded-2xl border border-surface-3 p-6 max-w-lg w-full max-h-[90vh] flex flex-col"
            >
                <h3 class="text-lg font-bold text-ink-900 mb-4 shrink-0">
                    {{ editingId ? "Edit Number" : "Add Numbers" }}
                </h3>

                <!-- Tabs (hidden while editing) -->
                <div v-if="!editingId" class="flex gap-2 mb-5 shrink-0">
                    <button
                        v-for="t in [
                            { key: 'file', label: 'Upload File' },
                            { key: 'manual', label: 'Manual Entry' },
                        ]"
                        :key="t.key"
                        type="button"
                        @click="tab = t.key"
                        :class="[
                            'px-4 py-2 rounded-full text-sm font-medium border transition',
                            tab === t.key
                                ? 'bg-primary text-ink-100 border-primary'
                                : 'bg-surface-3 text-ink-500 border-surface-3 hover:text-ink-900',
                        ]"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <!-- File tab -->
                <div
                    v-if="tab === 'file' && !editingId"
                    class="space-y-4 overflow-y-auto flex-1 pr-1"
                >
                    <p class="text-xs text-ink-500">
                        Upload a CSV with the columns
                        <span class="font-mono text-ink-700"
                            >mobile, serial, provider, country</span
                        >
                        (mobile and serial are required). The file is saved on
                        the server and a file with the same name can't be
                        uploaded again.
                    </p>
                    <a
                        href="/admin/allowed-numbers/sample"
                        class="inline-block text-xs px-3 py-1.5 bg-surface-3 border border-surface-3 text-ink-500 rounded-lg hover:text-ink-900 transition"
                    >
                        Download Sample CSV
                    </a>
                    <div>
                        <label :class="labelClass">CSV File</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.txt"
                            @change="onFileChange"
                            class="block w-full text-sm text-ink-500 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-surface-3 file:text-ink-900"
                        />
                        <InputError
                            class="mt-1"
                            :message="fileForm.errors.file"
                        />
                    </div>
                </div>

                <!-- Manual tab -->
                <div v-else class="space-y-4 overflow-y-auto flex-1 pr-1">
                    <div>
                        <label :class="labelClass">Mobile</label>
                        <input
                            v-model="manualForm.mobile"
                            type="text"
                            :class="inputClass"
                            placeholder="447700900123"
                        />
                        <InputError
                            class="mt-1"
                            :message="manualForm.errors.mobile"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Serial</label>
                        <input
                            v-model="manualForm.serial"
                            type="text"
                            :class="inputClass"
                            placeholder="829953289034771924"
                        />
                        <InputError
                            class="mt-1"
                            :message="manualForm.errors.serial"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Provider</label>
                        <input
                            v-model="manualForm.provider"
                            type="text"
                            :class="inputClass"
                            placeholder="e.g. giffgaff"
                        />
                        <InputError
                            class="mt-1"
                            :message="manualForm.errors.provider"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Country</label>
                        <input
                            v-model="manualForm.country"
                            type="text"
                            :class="inputClass"
                            placeholder="e.g. GB"
                            maxlength="10"
                        />
                        <InputError
                            class="mt-1"
                            :message="manualForm.errors.country"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Note</label>
                        <input
                            v-model="manualForm.note"
                            type="text"
                            :class="inputClass"
                            placeholder="Optional note"
                        />
                        <InputError
                            class="mt-1"
                            :message="manualForm.errors.note"
                        />
                    </div>
                    <div v-if="editingId" class="flex items-center gap-2">
                        <input
                            v-model="manualForm.active"
                            type="checkbox"
                            id="active"
                            class="rounded border-surface-3"
                        />
                        <label for="active" class="text-sm text-ink-500"
                            >Active</label
                        >
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 mt-6 shrink-0">
                    <button
                        v-if="tab === 'file' && !editingId"
                        @click="submitFile"
                        :disabled="!fileForm.file || fileForm.processing"
                        class="flex-1 py-2.5 bg-primary text-ink-100 rounded-xl font-medium hover:bg-primary-dark disabled:opacity-60 disabled:cursor-not-allowed transition"
                    >
                        {{
                            fileForm.processing
                                ? "Uploading..."
                                : "Upload & Import"
                        }}
                    </button>
                    <button
                        v-else
                        @click="submitManual"
                        :disabled="manualForm.processing"
                        class="flex-1 py-2.5 bg-primary text-ink-100 rounded-xl font-medium hover:bg-primary-dark disabled:opacity-60 transition"
                    >
                        {{ editingId ? "Update" : "Add" }}
                    </button>
                    <button
                        @click="showModal = false"
                        class="flex-1 py-2.5 bg-surface-3 text-ink-900 rounded-xl font-medium transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>