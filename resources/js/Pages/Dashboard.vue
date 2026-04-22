<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    applications: {
        type: Array,
        required: true,
    },
    statusCounts: {
        type: Object,
        required: true,
    },
    totalApplications: {
        type: Number,
        required: true,
    },
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    matchedJobsCount: {
        type: Number,
        required: true,
    },
    resumeReady: {
        type: Boolean,
        required: true,
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                title="Resume-first job matching"
                subtitle="Paste your resume once, let the app detect overlap, and jump straight into the jobs that already match your background."
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="props.resumeReady ? route('matched-jobs.index') : route('resume-profile.show')"
                            class="premium-button-primary"
                        >
                            {{ props.resumeReady ? 'View matched jobs' : 'Set up resume' }}
                        </Link>
                        <Link
                            :href="route('resume-profile.show')"
                            class="premium-button-secondary"
                        >
                            Resume setup
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Start here"
                description="The shortest path to value is resume first. Matching begins after the system has your base resume text."
            >
                <EmptyState
                    v-if="!resumeReady"
                    title="Upload or paste your resume first"
                    description="Matching starts after you add your base resume text. Once that is in place, the app can surface jobs with overlapping keywords and skills."
                >
                    <Link
                        :href="route('resume-profile.show')"
                        class="premium-button-primary"
                    >
                        Set up resume
                    </Link>
                </EmptyState>

                <div v-else class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-[2rem] border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                            Matching active
                        </p>
                        <h2 class="mt-3 text-3xl font-semibold text-white">
                            {{ matchedJobsCount }} matched job{{ matchedJobsCount === 1 ? '' : 's' }} ready for review
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-slateglass-300">
                            Focus on jobs with real overlap first. Each card shows matched keywords, missing keywords, and a direct source link so you can move quickly.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('matched-jobs.index')"
                                class="premium-button-primary"
                            >
                                View matched jobs
                            </Link>
                            <Link
                                :href="route('resume-profile.show')"
                                class="premium-button-secondary"
                            >
                                Update resume
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.03] p-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slateglass-400">
                            Secondary workflow
                        </p>
                        <h3 class="mt-3 text-xl font-semibold text-white">
                            Applications stay available later
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-slateglass-300">
                            The product is centered on resume matching and job discovery. Application tracking remains available, but it is not the main path to value.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('applications.index')"
                                class="premium-button-secondary"
                            >
                                Open applications
                            </Link>
                            <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-slateglass-300">
                                {{ totalApplications }} tracked application{{ totalApplications === 1 ? '' : 's' }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
