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
            <h1 class="text-3xl font-bold text-white mb-1">Settings</h1>
            <p class="text-dark-300">Platform configuration</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <template v-for="group in groups" :key="group">
                <div class="bg-dark-800 rounded-2xl p-6 border border-dark-600">
                    <h3
                        class="text-lg font-semibold text-white mb-4 capitalize"
                    >
                        {{ group }}
                    </h3>
                    <div class="space-y-4">
                        <div
                            v-for="setting in settings[group]"
                            :key="setting.id"
                            class="flex items-center gap-4"
                        >
                            <label class="w-1/3 text-sm text-dark-200">{{
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
                                class="flex-1 border border-dark-600 rounded-lg px-3 py-2 text-sm bg-dark-700 text-white input-dark"
                            />
                        </div>
                    </div>
                </div>
            </template>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:opacity-50 transition"
            >
                Save Settings
            </button>
        </form>
    </div>
</template>
