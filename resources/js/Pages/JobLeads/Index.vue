<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    matchedJobs: {
        type: Array,
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

const filterForm = reactive({
    search: props.filters.search || '',
});

function submitFilters() {
    router.get(route('matched-jobs.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.search = '';
    submitFilters();
}

function t(path, fallback) {
    const value = path.split('.').reduce((carry, key) => carry?.[key], page.props.translations);

    return value ?? fallback ?? path;
}
</script>

<template>
    <Head :title="t('nav.matched_jobs', 'Matched Jobs')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                :title="t('matched_jobs.title', 'Matched jobs')"
                :subtitle="t('matched_jobs.subtitle', 'See jobs that overlap with your resume, review matched and missing keywords, and go straight to the source listing.')"
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="route('resume-profile.show')"
                            class="premium-button-primary"
                        >
                            {{ resumeReady
                                ? t('resume.update_setup', 'Update resume setup')
                                : t('buttons.set_up_resume', 'Set up resume') }}
                        </Link>
                        <Link
                            :href="route('job-leads.create')"
                            class="premium-button-secondary"
                        >
                            {{ t('buttons.add_job_source', 'Add job source') }}
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <PageHeader
                :eyebrow="t('matched_jobs.eyebrow', 'Core product')"
                :title="t('matched_jobs.title', 'Matched jobs')"
                :description="t('matched_jobs.page_description', 'This workspace is focused on immediate signal: overlap with your resume, missing keywords to address, and direct links to the original job source.')"
            >
                <Link
                    :href="route('resume-profile.show')"
                    class="premium-button-primary"
                >
                    {{ t('buttons.resume_setup', 'Resume setup') }}
                </Link>
                <Link
                    :href="route('job-leads.create')"
                    class="premium-button-secondary"
                >
                    {{ t('buttons.add_job_source', 'Add job source') }}
                </Link>
            </PageHeader>

            <SectionCard
                :title="t('matched_jobs.filter_title', 'Find a match faster')"
                :description="t('matched_jobs.filter_description', 'Search by company or role. The list is already narrowed to jobs with at least one detected match when your resume is ready.')"
            >
                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[1fr_auto]">
                    <div>
                        <label for="search" class="premium-input-label">{{ t('matched_jobs.search', 'Search') }}</label>
                        <input
                            id="search"
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 block w-full"
                            :placeholder="t('matched_jobs.search_placeholder', 'Company or job title')"
                        >
                    </div>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="premium-button-primary"
                        >
                            {{ t('matched_jobs.apply', 'Apply') }}
                        </button>
                        <button
                            type="button"
                            class="premium-button-secondary"
                            @click="resetFilters"
                        >
                            {{ t('matched_jobs.reset', 'Reset') }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard
                :title="t('matched_jobs.results_title', 'Matching results')"
                :description="t('matched_jobs.results_description', 'Job cards are simplified to the signals that matter most right now: overlap, gaps, and direct source access.')"
                :padded="false"
            >
                <template #actions>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                        {{ matchedJobs.length }} {{ t('matched_jobs.visible', 'visible') }}
                    </span>
                </template>

                <EmptyState
                    v-if="!resumeReady"
                    :title="t('matched_jobs.empty_resume_title', 'Matching starts after resume upload')"
                    :description="resumeNeedsTextInput
                        ? 'Your resume file is saved, but matching still needs plain resume text. TXT uploads work immediately. For PDF, DOC, or DOCX, paste resume text or add core skills first.'
                        : t('matched_jobs.empty_resume_description', 'Upload your resume first. Once it is ready, this page will surface only jobs with detected overlap.')"
                >
                    <Link
                        :href="route('resume-profile.show')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.set_up_resume', 'Set up resume') }}
                    </Link>
                </EmptyState>

                <EmptyState
                    v-else-if="matchedJobs.length === 0"
                    :title="t('matched_jobs.empty_matches_title', 'No matched jobs yet')"
                    :description="t('matched_jobs.empty_matches_description', 'Your resume is ready, but there are no leads with detected overlap right now.')"
                >
                    <Link
                        :href="route('job-leads.create')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.add_job_source', 'Add job source') }}
                    </Link>
                    <Link
                        :href="route('job-leads.create')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.import_from_url', 'Import from URL') }}
                    </Link>
                </EmptyState>

                <div
                    v-else
                    class="divide-y divide-white/10"
                >
                    <div
                        v-for="jobLead in matchedJobs"
                        :key="jobLead.id"
                        class="px-6 py-6"
                    >
                        <div class="rounded-[1.75rem] border border-white/10 bg-white/[0.03] p-6 shadow-panel">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-xl font-semibold text-white">
                                    {{ jobLead.company_name }}
                                </h3>
                            </div>

                            <p class="mt-2 text-sm text-slateglass-300">
                                {{ jobLead.job_title }}
                            </p>
                            <div class="mt-6 grid gap-4 xl:grid-cols-2">
                                <div class="rounded-3xl border border-emerald-400/12 bg-emerald-400/[0.05] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/90">
                                        {{ t('matched_jobs.matched_keywords', 'Matched keywords') }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span
                                            v-for="keyword in jobLead.matched_keywords"
                                            :key="keyword"
                                            class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200"
                                        >
                                            {{ keyword }}
                                        </span>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-gold-300/12 bg-gold-300/[0.05] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold-300/90">
                                        {{ t('matched_jobs.missing_keywords', 'Missing keywords') }}
                                    </p>
                                    <div
                                        v-if="jobLead.missing_keywords.length > 0"
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="keyword in jobLead.missing_keywords"
                                            :key="keyword"
                                            class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
                                        >
                                            {{ keyword }}
                                        </span>
                                    </div>
                                    <p v-else class="mt-3 text-sm text-slateglass-300">
                                        {{ t('matched_jobs.no_missing_keywords', 'No obvious keyword gaps right now') }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="jobLead.ats_hint"
                                class="mt-5 text-sm leading-6 text-slateglass-400"
                            >
                                {{ jobLead.ats_hint }}
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <a
                                    v-if="jobLead.source_url"
                                    :href="jobLead.source_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="premium-button-primary"
                                >
                                    {{ t('buttons.go_to_job', 'Go to job') }}
                                </a>
                                <Link
                                    :href="route('job-leads.edit', jobLead.id)"
                                    class="premium-button-secondary"
                                >
                                    {{ t('matched_jobs.review_match', 'Review match') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
