<script setup>
import { Head, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref, computed } from "vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({ operators: Object, countries: Array });

const syncing = ref(false);
const search = ref(props.operators.query?.search || "");
const countryFilter = ref(props.operators.query?.country_id || "");
const countrySearch = ref("");
const showCountryDropdown = ref(false);

const filteredCountries = computed(() => {
    if (!countrySearch.value) return props.countries;
    const q = countrySearch.value.toLowerCase();
    return props.countries.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.iso_code.toLowerCase().includes(q) ||
            (c.flag_emoji && c.flag_emoji.includes(q)),
    );
});

const selectedCountryName = computed(() => {
    if (!countryFilter.value) return "";
    const c = props.countries.find((c) => c.id == countryFilter.value);
    return c ? `${c.flag_emoji || ""} ${c.name}` : "";
});

function selectCountry(id) {
    countryFilter.value = id;
    countrySearch.value = "";
    showCountryDropdown.value = false;
    applyFilters();
}

function closeCountryDropdown() {
    showCountryDropdown.value = false;
    countrySearch.value = "";
}

function syncFromDing() {
    syncing.value = true;
    router.post(
        "/admin/operators/sync",
        {},
        {
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function goToPage(url) {
    if (url) {
        router.get(url, {}, { preserveState: true });
    }
}

function applyFilters() {
    router.get(
        "/admin/operators",
        { search: search.value, country_id: countryFilter.value },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Operators - Admin" />

    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white mb-1">Operators</h1>
                <p class="text-dark-300">
                    {{ operators.total }} operator{{
                        operators.total !== 1 ? "s" : ""
                    }}
                    across {{ countries.length }} countries
                </p>
            </div>
            <div class="flex gap-3">
                <button
                    @click="syncFromDing"
                    :disabled="syncing"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition flex items-center gap-2 whitespace-nowrap"
                >
                    <svg
                        v-if="syncing"
                        class="animate-spin h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        ></path>
                    </svg>
                    {{ syncing ? "Syncing..." : "Sync from Ding" }}
                </button>
            </div>
        </div>

        <div
            v-if="$page.props.flash?.success"
            class="p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300"
        >
            {{ $page.props.flash.error }}
        </div>

        <div
            class="bg-dark-800 rounded-2xl border border-dark-600 overflow-visible"
        >
            <div class="p-4 border-b border-dark-600 flex flex-wrap gap-4">
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search operators..."
                    class="border border-dark-600 rounded-lg px-4 py-2 bg-dark-700 text-white text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                />
                <div class="relative">
                    <button
                        @click="showCountryDropdown = !showCountryDropdown"
                        class="border border-dark-600 rounded-lg px-4 py-2 bg-dark-700 text-white text-sm outline-none min-w-[180px] text-left flex items-center justify-between"
                    >
                        <span>{{
                            selectedCountryName || "All Countries"
                        }}</span>
                        <svg
                            class="w-4 h-4 text-dark-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            ></path>
                        </svg>
                    </button>
                    <div
                        v-if="showCountryDropdown"
                        class="absolute z-50 mt-1 w-80 bg-dark-700 border border-dark-600 rounded-lg shadow-xl max-h-80 flex flex-col"
                    >
                        <div class="p-2 border-b border-dark-600">
                            <input
                                v-model="countrySearch"
                                type="text"
                                placeholder="Search countries..."
                                class="w-full border border-dark-600 rounded px-3 py-2 bg-dark-800 text-white text-sm outline-none"
                            />
                        </div>
                        <div class="overflow-y-auto flex-1">
                            <div
                                @click="selectCountry('', null)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-dark-600 transition"
                                :class="
                                    !countryFilter
                                        ? 'text-primary font-medium'
                                        : 'text-dark-200'
                                "
                            >
                                All Countries
                            </div>
                            <div
                                v-for="c in filteredCountries"
                                :key="c.id"
                                @click="selectCountry(c.id, c.name)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-dark-600 transition flex items-center gap-2"
                                :class="
                                    countryFilter == c.id
                                        ? 'bg-dark-600 text-primary font-medium'
                                        : 'text-dark-200'
                                "
                            >
                                <span>{{ c.flag_emoji || "" }}</span>
                                <span>{{ c.name }}</span>
                                <span class="text-dark-400 text-xs ml-auto">{{
                                    c.iso_code
                                }}</span>
                            </div>
                            <div
                                v-if="!filteredCountries.length"
                                class="px-3 py-4 text-center text-dark-400 text-sm"
                            >
                                No countries found
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-600">
                    <thead class="bg-dark-700">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Provider
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Provider Code
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Country
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-dark-200 uppercase"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-600">
                        <tr
                            v-for="op in operators.data"
                            :key="op.id"
                            class="hover:bg-dark-700 transition"
                        >
                            <td
                                class="px-4 py-3 text-sm font-medium text-white"
                            >
                                {{ op.name }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm font-mono text-dark-300"
                            >
                                {{ op.provider_code || "-" }}
                            </td>
                            <td class="px-4 py-3 text-sm text-dark-200">
                                {{ op.country?.flag_emoji }}
                                {{ op.country?.name || "-" }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        op.is_active
                                            ? 'bg-green-500/20 text-green-300'
                                            : 'bg-dark-600 text-dark-400',
                                    ]"
                                >
                                    {{ op.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!operators.data?.length">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-dark-400"
                            >
                                No operators found. Click "Sync from Ding" to
                                import.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="operators.last_page > 1"
                class="p-4 border-t border-dark-600 flex items-center justify-between flex-wrap gap-2"
            >
                <p class="text-sm text-dark-400">
                    Showing {{ operators.from }} to {{ operators.to }} of
                    {{ operators.total }}
                </p>
                <div class="flex gap-2">
                    <button
                        v-for="(link, key) in operators.links"
                        :key="key"
                        @click="goToPage(link.url)"
                        :disabled="!link.url || link.active"
                        :class="[
                            'px-3 py-1 rounded text-sm transition',
                            link.active
                                ? 'bg-primary text-white'
                                : 'bg-dark-700 text-dark-300 hover:bg-dark-600',
                            !link.url ? 'opacity-50 cursor-not-allowed' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
