<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({ settings: Object, groups: Array });

const flatSettings = Object.values(props.settings).flat();
const form = useForm({
    settings: flatSettings.map((s) => ({ id: s.id, value: s.value })),
});

function submit() {
    form.put("/admin/settings", { onSuccess: () => alert("Settings saved!") });
}
</script>

<template>
    <Head title="Settings - Admin" />
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-ink-900 mb-1">Settings</h1>
            <p class="text-ink-500">Platform configuration</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <template v-for="group in groups" :key="group">
                <div class="bg-surface-2 rounded-2xl p-6 border border-surface-3">
                    <h3
                        class="text-lg font-semibold text-ink-900 mb-4 capitalize"
                    >
                        {{ group }}
                    </h3>
                    <div class="space-y-4">
                        <div
                            v-for="setting in settings[group]"
                            :key="setting.id"
                            class="flex items-center gap-4"
                        >
                            <label class="w-1/3 text-sm text-ink-700">{{
                                setting.description || setting.key
                            }}</label>
                            <input
                                :value="setting.value"
                                @input="
                                    (e) => {
                                        const s = form.settings.find(
                                            (x) => x.id === setting.id,
                                        );
                                        if (s) s.value = e.target.value;
                                    }
                                "
                                type="text"
                                class="flex-1 border border-surface-3 rounded-lg px-3 py-2 text-sm bg-surface-3 text-ink-900 input-dark"
                            />
                        </div>
                    </div>
                </div>
            </template>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-2 bg-primary text-ink-900 rounded-lg hover:bg-primary-dark disabled:opacity-60 transition"
            >
                Save Settings
            </button>
        </form>
    </div>
</template>
