<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

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
    resumeNeedsTextInput: {
        type: Boolean,
        required: true,
    },
});

const page = usePage();

function t(path, fallback) {
    const value = path.split('.').reduce((carry, key) => carry?.[key], page.props.translations);

    return value ?? fallback ?? path;
}
</script>

<template>
    <Head :title="t('nav.dashboard', 'Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                :title="t('dashboard.title', 'Resume-first job matching')"
                :subtitle="t('dashboard.subtitle', 'Upload your resume once, then move directly into the jobs that already match your background.')"
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="props.resumeReady ? route('matched-jobs.index') : route('resume-profile.show')"
                            class="premium-button-primary"
                        >
                            {{ props.resumeReady
                                ? t('buttons.view_matched_jobs', 'View matched jobs')
                                : t('buttons.set_up_resume', 'Set up resume') }}
                        </Link>
                        <Link
                            :href="route('resume-profile.show')"
                            class="premium-button-secondary"
                        >
                            {{ t('buttons.resume_setup', 'Resume setup') }}
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                :title="t('dashboard.start_title', 'Start here')"
                :description="t('dashboard.start_description', 'The shortest path to value is resume first. Matching begins after the app has your resume text.')"
            >
                <EmptyState
                    v-if="!resumeReady"
                    :title="t('dashboard.empty_title', 'Upload your resume first')"
                    :description="resumeNeedsTextInput
                        ? 'Your resume file is saved, but matching still needs plain resume text. TXT uploads work immediately. For PDF, DOC, or DOCX, paste resume text or add core skills first.'
                        : t('dashboard.empty_description', 'Matching starts after you add a resume. Once it is ready, the app can surface jobs with overlapping keywords and skills.')"
                >
                    <Link
                        :href="route('resume-profile.show')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.set_up_resume', 'Set up resume') }}
                    </Link>
                </EmptyState>

                <div v-else class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-[2rem] border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                            {{ t('dashboard.active_label', 'Matching active') }}
                        </p>
                        <h2 class="mt-3 text-3xl font-semibold text-white">
                            {{ matchedJobsCount }} {{ t('dashboard.matched_ready', 'matched jobs ready for review') }}
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-slateglass-300">
                            {{ t('dashboard.active_description', 'Focus on jobs with real overlap first. Each card shows matched keywords, missing keywords, and a direct source link.') }}
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('matched-jobs.index')"
                                class="premium-button-primary"
                            >
                                {{ t('buttons.view_matched_jobs', 'View matched jobs') }}
                            </Link>
                            <Link
                                :href="route('job-leads.create')"
                                class="premium-button-secondary"
                            >
                                {{ t('buttons.add_job_source', 'Add job source') }}
                            </Link>
                            <Link
                                :href="route('resume-profile.show')"
                                class="premium-button-secondary"
                            >
                                {{ t('resume.update_setup', 'Update resume setup') }}
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.03] p-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slateglass-400">
                            {{ t('dashboard.secondary_label', 'Secondary workflow') }}
                        </p>
                        <h3 class="mt-3 text-xl font-semibold text-white">
                            {{ t('dashboard.secondary_title', 'Applications stay secondary') }}
                        </h3>
                        <p class="mt-4 text-sm leading-7 text-slateglass-300">
                            {{ t('dashboard.secondary_description', 'The product is centered on resume matching and discovery. Application tracking remains available, but it is not the main path to value.') }}
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('applications.index')"
                                class="premium-button-secondary"
                            >
                                {{ t('dashboard.open_applications', 'Open applications') }}
                            </Link>
                            <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-slateglass-300">
                                {{ totalApplications }} {{ t('dashboard.tracked_applications', 'tracked applications') }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
