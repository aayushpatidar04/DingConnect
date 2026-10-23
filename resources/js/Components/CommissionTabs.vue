<script setup>
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage();

const tabs = [
    { label: "Levels & Rates", href: "/admin/commission-levels" },
    { label: "Retailer Levels", href: "/admin/retailer-levels" },
    { label: "Commission Report", href: "/admin/commissions" },
];

const isActive = (href) => page.url.startsWith(href);
</script>

<template>
    <div class="flex flex-wrap gap-2 border-b border-surface-3 pb-3">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="px-4 py-2 rounded-lg text-sm font-medium transition"
            :class="
                isActive(tab.href)
                    ? 'bg-primary text-ink-100'
                    : 'text-ink-500 hover:text-ink-900 hover:bg-surface-2'
            "
        >
            {{ tab.label }}
        </Link>
    </div>
</template>
