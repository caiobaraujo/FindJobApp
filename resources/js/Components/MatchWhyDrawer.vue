<script setup>
defineProps({
    resumeSkills: {
        type: Array,
        required: true,
    },
    jobKeywords: {
        type: Array,
        required: true,
    },
    matchedKeywords: {
        type: Array,
        required: true,
    },
    missingKeywords: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <details class="mt-5 rounded-3xl border border-white/10 bg-black/20 p-5">
        <summary class="cursor-pointer list-none text-sm font-medium text-slateglass-200">
            Why this matched
        </summary>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                    Detected resume skills
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="skill in resumeSkills"
                        :key="skill"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slateglass-200"
                    >
                        {{ skill }}
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                    Job keywords used
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="keyword in jobKeywords"
                        :key="keyword"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slateglass-200"
                    >
                        {{ keyword }}
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-400/12 bg-emerald-400/[0.05] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">
                    Matched keywords
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="keyword in matchedKeywords"
                        :key="keyword"
                        class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs text-emerald-200"
                    >
                        {{ keyword }}
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-gold-300/12 bg-gold-300/[0.05] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold-300/90">
                    Missing keywords
                </p>
                <div
                    v-if="missingKeywords.length > 0"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <span
                        v-for="keyword in missingKeywords"
                        :key="keyword"
                        class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs text-gold-200"
                    >
                        {{ keyword }}
                    </span>
                </div>
                <p
                    v-else
                    class="mt-3 text-sm text-slateglass-300"
                >
                    No missing keywords right now.
                </p>
            </div>
        </div>
    </details>
</template>
