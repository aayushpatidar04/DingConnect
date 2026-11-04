<script setup>
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import RetailerLayout from "@/Layouts/RetailerLayout.vue";
import FlashMessage from "@/Components/FlashMessage.vue";

defineOptions({ layout: RetailerLayout });

const props = defineProps({
    posters: { type: Array, default: () => [] },
});

const now = new Date();

const activePosters = computed(() =>
    props.posters.filter((poster) => {
        if (poster.status !== "active") return false;

        if (!poster.expires_at) return true;

        const expiry = new Date(poster.expires_at);

        return !Number.isNaN(expiry.getTime()) && expiry >= now;
    }),
);

const expiredCount = computed(
    () => props.posters.filter((poster) => poster.status === "expired").length,
);

function posterTypeLabel(type) {
    const labels = {
        festive_greeting: "Festive Greeting",
        announcement: "Announcement",
        advertisement: "Advertisement",
    };

    return labels[type] || "Advertisement";
}

function formatDate(date) {
    if (!date) return null;

    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) return null;

    return parsed.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}
</script>

<template>
    <Head title="Posters - MK All Network Communications" />
    <FlashMessage />

    <div class="space-y-6 pb-8">
        <!-- Page heading -->
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-ink-900 mb-1">
                Posters
            </h1>

            <p class="text-sm sm:text-base text-ink-500">
                Latest announcements, festive greetings and advertisements for
                you.
            </p>
        </div>

        <!-- Social media style feed -->
        <div v-if="activePosters.length" class="max-w-2xl mx-auto space-y-6">
            <article
                v-for="poster in activePosters"
                :key="poster.id"
                class="bg-white rounded-2xl border border-surface-3 overflow-hidden shadow-sm"
            >
                <!-- Post header -->
                <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-11 h-11 shrink-0 rounded-full bg-primary/10 flex items-center justify-center"
                        >
                            <svg
                                class="w-6 h-6 text-primary"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M3 7h18M5 7l1 13h12l1-13M9 7V4h6v3"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h3
                                class="font-semibold text-sm sm:text-base text-ink-900 truncate"
                            >
                                MK All Network Communications
                            </h3>

                            <p class="text-xs text-ink-500 mt-0.5">
                                {{ posterTypeLabel(poster.poster_type) }}
                            </p>
                        </div>
                    </div>

                    <span
                        class="shrink-0 px-3 py-1 rounded-full text-[10px] sm:text-xs font-semibold bg-primary/10 text-primary"
                    >
                        {{ posterTypeLabel(poster.poster_type) }}
                    </span>
                </div>

                <!-- Poster image: original dimensions preserved -->
                <div
                    v-if="poster.image_url"
                    class="poster-media relative w-full flex items-center justify-center bg-slate-100"
                >
                    <a
                        v-if="poster.link_url"
                        :href="poster.link_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block w-full text-center"
                        :aria-label="`Open link for ${poster.title}`"
                    >
                        <img
                            :src="poster.image_url"
                            :alt="poster.title || 'Advertisement poster'"
                            class="poster-image"
                            loading="lazy"
                            decoding="async"
                        />
                    </a>

                    <img
                        v-else
                        :src="poster.image_url"
                        :alt="poster.title || 'Advertisement poster'"
                        class="poster-image"
                        loading="lazy"
                        decoding="async"
                    />
                </div>

                <!-- Image unavailable -->
                <div
                    v-else
                    class="w-full aspect-[4/3] bg-surface-2 flex items-center justify-center"
                >
                    <div class="text-center text-ink-400">
                        <svg
                            class="w-14 h-14 mx-auto mb-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>

                        <p class="text-sm">Image unavailable</p>
                    </div>
                </div>

                <!-- Post content -->
                <div class="p-4 sm:p-5">
                    <h2
                        class="text-base sm:text-lg font-semibold text-ink-900 leading-snug"
                    >
                        {{ poster.title }}
                    </h2>

                    <p
                        v-if="poster.description"
                        class="text-sm text-ink-600 mt-2 whitespace-pre-line break-words"
                    >
                        {{ poster.description }}
                    </p>

                    <!-- Post actions -->
                    <div v-if="poster.link_url" class="mt-4">
                        <a
                            :href="poster.link_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:opacity-90 transition"
                        >
                            View more

                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 17L17 7M7 7h10v10"
                                />
                            </svg>
                        </a>
                    </div>

                    <!-- Expiry information -->
                    <div
                        v-if="
                            poster.expires_at && formatDate(poster.expires_at)
                        "
                        class="mt-4 pt-3 border-t border-surface-3 text-xs text-ink-500"
                    >
                        Available until {{ formatDate(poster.expires_at) }}
                    </div>
                </div>
            </article>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="bg-surface-2 rounded-2xl border border-surface-3 p-10 sm:p-16 text-center"
        >
            <svg
                class="w-16 h-16 mx-auto text-ink-300 mb-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 10V3L4 14h7v7l9-11h-7z"
                />
            </svg>

            <h3 class="text-lg font-semibold text-ink-900 mb-2">
                No posters right now
            </h3>

            <p class="text-sm text-ink-500 max-w-xs mx-auto">
                Check back later for new announcements and festive greetings.
            </p>
        </div>

        <!-- Expired notice -->
        <div
            v-if="expiredCount > 0"
            class="max-w-2xl mx-auto p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800"
        >
            {{ expiredCount }} poster(s) have expired and are no longer visible.
        </div>
    </div>
</template>

<style scoped>
.poster-media {
    min-height: 180px;
    overflow: hidden;
}

/*
 * Keep the original image dimensions.
 * No fixed height and no object-cover.
 */
.poster-image {
    display: block;
    width: 100%;
    height: auto;
    max-height: 80vh;
    object-fit: contain;
    object-position: center;
    margin: 0 auto;
}

/* Mobile screens */
@media (max-width: 640px) {
    .poster-image {
        max-height: 72svh;
    }
}
</style>
