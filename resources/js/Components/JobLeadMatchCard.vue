<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    analysis: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                    Resume match
                </p>
                <h3 class="mt-2 text-lg font-semibold text-white">
                    Base resume coverage
                </h3>
            </div>
            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                {{ analysis.matched_keywords.length }} matched
            </span>
        </div>

        <p class="mt-4 text-sm leading-6 text-slateglass-300">
            {{ analysis.match_summary }}
        </p>

        <div v-if="analysis.state === 'missing_profile'" class="mt-5 rounded-3xl border border-white/10 bg-black/20 p-5">
            <p class="text-sm text-slateglass-300">
                No resume profile yet
            </p>
            <Link
                :href="route('resume-profile.show')"
                class="mt-4 inline-flex premium-button-primary"
            >
                Create resume profile
            </Link>
        </div>

        <div v-else-if="analysis.state === 'missing_job_analysis'" class="mt-5 rounded-3xl border border-white/10 bg-black/20 p-5">
            <p class="text-sm text-slateglass-300">
                No job description or extracted keywords yet
            </p>
        </div>

        <div v-else class="mt-6 grid gap-6 xl:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                    Matched keywords
                </p>
                <div v-if="analysis.matched_keywords.length > 0" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="keyword in analysis.matched_keywords"
                        :key="keyword"
                        class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200"
                    >
                        {{ keyword }}
                    </span>
                </div>
                <p v-else class="mt-3 text-sm text-slateglass-400">
                    No matched keywords yet
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                    Missing keywords
                </p>
                <div v-if="analysis.missing_keywords.length > 0" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="keyword in analysis.missing_keywords"
                        :key="keyword"
                        class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
                    >
                        {{ keyword }}
                    </span>
                </div>
                <p v-else class="mt-3 text-sm text-slateglass-400">
                    No missing keywords right now
                </p>
            </div>
        </div>
    </div>
</template>
