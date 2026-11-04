<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";
import InputError from "@/Components/InputError.vue";
import MultiPicker from "@/Components/MultiPicker.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    posters: { type: Array, default: () => [] },
    commissionLevels: { type: Array, default: () => [] },
    retailers: { type: Array, default: () => [] },
});

const showModal = ref(false);
const processingAction = ref(null);
const imagePreview = ref(null);
const existingImageUrl = ref(null);
const fileInputKey = ref(0);

const labelClass = "block text-sm font-medium text-ink-700 mb-1.5";
const inputClass =
    "w-full rounded-lg border border-surface-3 bg-white px-3 py-2 text-sm text-ink-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20";

const form = useForm({
    id: null,
    title: "",
    description: "",
    link_url: "",
    poster_type: "advertisement",
    audience: "all",
    level_ids: [],
    retailer_ids: [],
    starts_at: "",
    expires_at: "",
    is_active: true,
    image: null,
    _method: "post",
});

const isEditing = computed(() => Boolean(form.id));
const audienceOptions = [
    { value: "all", label: "All retailers" },
    { value: "levels", label: "Selected commission levels" },
    { value: "retailers", label: "Selected retailers" },
];

function toDateTimeLocal(value) {
    if (!value) return "";
    // Handle both ISO timestamps and SQL datetime values without shifting the displayed clock.
    return String(value).replace(" ", "T").slice(0, 16);
}

function isPosterActive(poster) {
    return (
        poster?.is_active === true ||
        poster?.is_active === 1 ||
        poster?.is_active === "1"
    );
}

function audienceDisplay(audience) {
    return (
        audienceOptions.find((item) => item.value === audience)?.label ??
        audience ??
        "-"
    );
}

function resetForm() {
    form.reset();
    form.clearErrors();
    form.id = null;
    form._method = "post";
    form.image = null;
    imagePreview.value = null;
    existingImageUrl.value = null;
    fileInputKey.value++;
}

function open(poster = null) {
    resetForm();

    if (poster) {
        form.id = poster.id;
        form.title = poster.title ?? "";
        form.description = poster.description ?? "";
        form.link_url = poster.link_url ?? "";
        form.poster_type = poster.poster_type ?? "advertisement";
        form.audience = poster.audience ?? "all";
        form.level_ids = Array.isArray(poster.level_ids)
            ? [...poster.level_ids]
            : [];
        form.retailer_ids = Array.isArray(poster.retailer_ids)
            ? [...poster.retailer_ids]
            : [];
        form.starts_at = toDateTimeLocal(poster.starts_at);
        form.expires_at = toDateTimeLocal(poster.expires_at);
        form.is_active = isPosterActive(poster);
        existingImageUrl.value = poster.image_url ?? null;
        imagePreview.value = existingImageUrl.value;
        form._method = "put";
    }

    showModal.value = true;
}

function closeModal() {
    if (form.processing) return;
    showModal.value = false;
    resetForm();
}

function onImageChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.image = file;

    if (!file) {
        imagePreview.value = existingImageUrl.value;
        return;
    }

    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        form.setError("image", "Please select a JPG, PNG or WebP image.");
        form.image = null;
        event.target.value = "";
        imagePreview.value = existingImageUrl.value;
        return;
    }

    if (file.size > 4 * 1024 * 1024) {
        form.setError("image", "The image must not be larger than 4 MB.");
        form.image = null;
        event.target.value = "";
        imagePreview.value = existingImageUrl.value;
        return;
    }

    form.clearErrors("image");
    const reader = new FileReader();
    reader.onload = () => {
        imagePreview.value = String(reader.result);
    };
    reader.readAsDataURL(file);
}

function removeSelectedImage() {
    form.image = null;
    imagePreview.value = existingImageUrl.value;
    fileInputKey.value++;
    form.clearErrors("image");
}

function submit() {
    form.clearErrors();

    if (form.audience !== "levels") form.level_ids = [];
    if (form.audience !== "retailers") form.retailer_ids = [];

    // Laravel/Symfony do not reliably parse multipart PUT requests. Send multipart POST
    // with _method=PUT so file uploads and normal validation work together.
    form._method = isEditing.value ? "put" : "post";
    const url = isEditing.value
        ? `/admin/posters/${form.id}`
        : "/admin/posters";

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            resetForm();
        },
    });
}

function remove(poster) {
    if (
        !window.confirm(
            `Delete “${poster.title}”? This action cannot be undone.`,
        )
    )
        return;

    processingAction.value = `delete-${poster.id}`;
    router.delete(`/admin/posters/${poster.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processingAction.value = null;
        },
    });
}

function formatDate(value) {
    if (!value) return "-";
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? "-"
        : date.toLocaleDateString("en-GB", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          });
}

function retailerCount(poster) {
    return Array.isArray(poster.retailer_ids) ? poster.retailer_ids.length : 0;
}
</script>

<template>
    <Head title="Posters - Admin" />
    <FlashMessage />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="mb-1 text-3xl font-bold text-ink-900">Posters</h1>
                <p class="text-ink-500">
                    Upload advertisements, festive greetings and announcements
                    for retailers.
                </p>
            </div>
            <button
                type="button"
                @click="open()"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-ink-100 transition hover:bg-primary-dark"
            >
                + New poster
            </button>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-surface-3 bg-surface-2"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-3">
                    <thead class="bg-surface-3">
                        <tr>
                            <th
                                v-for="heading in [
                                    'Poster',
                                    'Type',
                                    'Audience',
                                    'Levels / Retailers',
                                    'Expires',
                                    'Status',
                                    '',
                                ]"
                                :key="heading"
                                class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-ink-700"
                            >
                                {{ heading }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3">
                        <tr v-for="poster in posters" :key="poster.id">
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="poster.image_url"
                                        :src="poster.image_url"
                                        :alt="poster.title"
                                        class="h-12 w-12 rounded-lg border border-surface-3 object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-surface-3 text-xs text-ink-500"
                                    >
                                        No image
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-ink-900">
                                            {{ poster.title }}
                                        </div>
                                        <div
                                            v-if="poster.description"
                                            class="max-w-[220px] truncate text-xs text-ink-500"
                                        >
                                            {{ poster.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm capitalize text-ink-700"
                            >
                                {{
                                    (poster.poster_type ?? "").replaceAll(
                                        "_",
                                        " ",
                                    )
                                }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-ink-700"
                            >
                                {{ audienceDisplay(poster.audience) }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-ink-700"
                            >
                                <span v-if="poster.audience === 'levels'">{{
                                    poster.level_names?.join(", ") || "-"
                                }}</span>
                                <span
                                    v-else-if="poster.audience === 'retailers'"
                                    >{{
                                        retailerCount(poster)
                                    }}
                                    retailer(s)</span
                                >
                                <span v-else>-</span>
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-ink-700"
                            >
                                {{ formatDate(poster.expires_at) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="rounded-full px-2 py-1 text-xs capitalize"
                                    :class="{
                                        'bg-green-500/20 text-green-600':
                                            poster.status === 'active',
                                        'bg-blue-500/20 text-blue-600':
                                            poster.status === 'scheduled',
                                        'bg-surface-3 text-ink-500':
                                            poster.status === 'paused',
                                        'bg-red-500/15 text-red-600':
                                            poster.status === 'expired',
                                    }"
                                    >{{
                                        poster.status ??
                                        (isPosterActive(poster)
                                            ? "active"
                                            : "paused")
                                    }}</span
                                >
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-right text-sm"
                            >
                                <button
                                    type="button"
                                    @click="open(poster)"
                                    class="mr-3 text-primary-light hover:text-primary"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    :disabled="
                                        processingAction ===
                                        `delete-${poster.id}`
                                    "
                                    @click="remove(poster)"
                                    class="text-red-600 hover:text-red-500 disabled:opacity-50"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!posters.length">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-sm text-ink-500"
                            >
                                No posters yet. Upload your first poster to get
                                started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="sticky top-0 z-10 flex items-center justify-between border-b border-surface-3 bg-white px-6 py-4"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-ink-900">
                            {{ isEditing ? "Edit poster" : "Create poster" }}
                        </h2>
                        <p class="mt-0.5 text-xs text-ink-500">
                            Fields marked required must be completed before
                            saving.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-lg px-3 py-1.5 text-2xl leading-none text-ink-500 hover:bg-surface-3"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>

                <form
                    @submit.prevent="submit"
                    enctype="multipart/form-data"
                    class="space-y-5 p-6"
                >
                    <div>
                        <label for="poster-title" :class="labelClass"
                            >Title <span class="text-red-500">*</span></label
                        >
                        <input
                            id="poster-title"
                            v-model="form.title"
                            type="text"
                            maxlength="150"
                            required
                            :class="inputClass"
                            placeholder="e.g. Diwali Festive Offer"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <label for="poster-description" :class="labelClass"
                            >Description
                            <span class="text-xs font-normal text-ink-500"
                                >(optional)</span
                            ></label
                        >
                        <textarea
                            id="poster-description"
                            v-model="form.description"
                            rows="3"
                            maxlength="2000"
                            :class="inputClass"
                            placeholder="Add a short description..."
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div>
                        <label for="poster-image" :class="labelClass"
                            >Poster image
                            <span v-if="!isEditing" class="text-red-500"
                                >*</span
                            ></label
                        >
                        <input
                            :key="fileInputKey"
                            id="poster-image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            :required="!isEditing && !imagePreview"
                            class="block w-full text-sm text-ink-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-semibold file:text-ink-100 hover:file:bg-primary-dark"
                            @change="onImageChange"
                        />
                        <p class="mt-1 text-xs text-ink-500">
                            JPG, PNG or WebP. Maximum file size: 4 MB. On edit,
                            leave blank to keep the current image.
                        </p>
                        <InputError :message="form.errors.image" />
                        <div
                            v-if="imagePreview"
                            class="mt-3 flex items-start gap-3"
                        >
                            <img
                                :src="imagePreview"
                                alt="Poster preview"
                                class="h-36 w-36 rounded-lg border border-surface-3 object-cover"
                            />
                            <button
                                v-if="form.image"
                                type="button"
                                @click="removeSelectedImage"
                                class="text-xs text-red-600 underline"
                            >
                                Remove selected file
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="poster-link" :class="labelClass"
                            >Link URL
                            <span class="text-xs font-normal text-ink-500"
                                >(optional)</span
                            ></label
                        >
                        <input
                            id="poster-link"
                            v-model="form.link_url"
                            type="url"
                            maxlength="2048"
                            :class="inputClass"
                            placeholder="https://example.com"
                        />
                        <InputError :message="form.errors.link_url" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="poster-type" :class="labelClass"
                                >Poster type
                                <span class="text-red-500">*</span></label
                            >
                            <select
                                id="poster-type"
                                v-model="form.poster_type"
                                required
                                :class="inputClass"
                            >
                                <option value="advertisement">
                                    Advertisement
                                </option>
                                <option value="festive_greeting">
                                    Festive greeting
                                </option>
                                <option value="announcement">
                                    Announcement
                                </option>
                            </select>
                            <InputError :message="form.errors.poster_type" />
                        </div>
                        <div>
                            <label for="poster-audience" :class="labelClass"
                                >Who can see it?
                                <span class="text-red-500">*</span></label
                            >
                            <select
                                id="poster-audience"
                                v-model="form.audience"
                                required
                                :class="inputClass"
                            >
                                <option
                                    v-for="option in audienceOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.audience" />
                        </div>
                    </div>

                    <div v-if="form.audience === 'levels'">
                        <label :class="labelClass"
                            >Commission levels
                            <span class="text-red-500">*</span></label
                        >
                        <MultiPicker
                            v-model="form.level_ids"
                            :options="commissionLevels"
                            placeholder="Search and select commission levels..."
                            empty-text="No commission levels found"
                        />
                        <InputError
                            :message="
                                form.errors.level_ids ||
                                form.errors['level_ids.0']
                            "
                        />
                    </div>

                    <div v-if="form.audience === 'retailers'">
                        <label :class="labelClass"
                            >Retailers
                            <span class="text-red-500">*</span></label
                        >
                        <MultiPicker
                            v-model="form.retailer_ids"
                            :options="retailers"
                            placeholder="Search and select retailers..."
                            empty-text="No retailers found"
                        />
                        <InputError
                            :message="
                                form.errors.retailer_ids ||
                                form.errors['retailer_ids.0']
                            "
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="poster-starts" :class="labelClass"
                                >Starts at
                                <span class="text-xs font-normal text-ink-500"
                                    >(optional)</span
                                ></label
                            >
                            <input
                                id="poster-starts"
                                v-model="form.starts_at"
                                type="datetime-local"
                                :class="inputClass"
                            />
                            <InputError :message="form.errors.starts_at" />
                        </div>
                        <div>
                            <label for="poster-expires" :class="labelClass"
                                >Expires at
                                <span class="text-xs font-normal text-ink-500"
                                    >(optional)</span
                                ></label
                            >
                            <input
                                id="poster-expires"
                                v-model="form.expires_at"
                                type="datetime-local"
                                :class="inputClass"
                            />
                            <InputError :message="form.errors.expires_at" />
                        </div>
                    </div>

                    <div class="rounded-lg border border-surface-3 p-3">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm font-medium text-ink-700"
                        >
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-surface-3 text-primary focus:ring-primary"
                            />
                            Publish as active
                        </label>
                        <p class="ml-6 mt-1 text-xs text-ink-500">
                            If disabled, the poster will remain paused until
                            resumed.
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap justify-end gap-2 border-t border-surface-3 pt-4"
                    >
                        <button
                            type="button"
                            :disabled="form.processing"
                            @click="closeModal"
                            class="rounded-lg border border-surface-3 px-4 py-2 text-sm text-ink-700 hover:bg-surface-3 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-ink-100 hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                            ></span>
                            {{
                                form.processing
                                    ? "Saving..."
                                    : isEditing
                                      ? "Update poster"
                                      : "Save poster"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
