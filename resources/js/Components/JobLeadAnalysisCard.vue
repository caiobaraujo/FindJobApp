<script setup>
defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
    hints: {
        type: Array,
        required: true,
    },
    keywords: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                    ATS analysis
                </p>
                <h3
                    v-if="!compact"
                    class="mt-2 text-lg font-semibold text-white"
                >
                    Resume tailoring signals
                </h3>
            </div>

            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                {{ keywords.length }} keywords
            </span>
        </div>

        <div class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                Extracted keywords
            </p>

            <div
                v-if="keywords.length > 0"
                class="mt-3 flex flex-wrap gap-2"
            >
                <span
                    v-for="keyword in keywords"
                    :key="keyword"
                    class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
                >
                    {{ keyword }}
                </span>
            </div>

            <p
                v-else
                class="mt-3 text-sm text-slateglass-400"
            >
                No keywords yet. Add the full description text to generate ATS signals.
            </p>
        </div>

        <div class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                ATS hints
            </p>

            <ul
                v-if="hints.length > 0"
                class="mt-3 space-y-2"
            >
                <li
                    v-for="hint in hints"
                    :key="hint"
                    class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm leading-6 text-slateglass-200"
                >
                    {{ hint }}
                </li>
            </ul>

            <p
                v-else
                class="mt-3 text-sm text-slateglass-400"
            >
                No ATS hints yet.
            </p>
        </div>
    </div>
</template>
