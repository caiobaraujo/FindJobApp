<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
});

const filterForm = reactive({
    search: props.filters.search || '',
});

function submitFilters() {
    router.get(route('job-leads.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.search = '';
    submitFilters();
}
</script>

<template>
    <Head title="Matched Jobs" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                title="Matched jobs"
                subtitle="See jobs that already overlap with your resume, review the matched and missing keywords, and jump straight to the source listing."
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="route('resume-profile.show')"
                            class="premium-button-primary"
                        >
                            {{ resumeReady ? 'Update resume' : 'Set up resume' }}
                        </Link>
                        <Link
                            :href="route('job-leads.import.entry')"
                            class="premium-button-secondary"
                        >
                            Add job source
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <PageHeader
                eyebrow="Core product"
                title="Matched jobs"
                description="This workspace is focused on immediate signal: overlap with your resume, missing keywords to address, and direct links to the original job source."
            >
                <Link
                    :href="route('resume-profile.show')"
                    class="premium-button-primary"
                >
                    Resume setup
                </Link>
                <Link
                    :href="route('job-leads.import.entry')"
                    class="premium-button-secondary"
                >
                    Add job source
                </Link>
            </PageHeader>

            <SectionCard
                title="Find a match faster"
                description="Search by company or role. The list is already narrowed to jobs with at least one detected match when your resume is ready."
            >
                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[1fr_auto]">
                    <div>
                        <label for="search" class="premium-input-label">Search</label>
                        <input
                            id="search"
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 block w-full"
                            placeholder="Company or job title"
                        >
                    </div>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="premium-button-primary"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            class="premium-button-secondary"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard
                title="Matching results"
                description="Lead cards are simplified to the signals that matter most right now: overlap, gaps, and direct source access."
                :padded="false"
            >
                <template #actions>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                        {{ matchedJobs.length }} visible
                    </span>
                </template>

                <EmptyState
                    v-if="!resumeReady"
                    title="Matching starts after resume upload"
                    description="Paste your base resume text first. Once the resume is ready, this page will surface only jobs with at least one detected overlap."
                >
                    <Link
                        :href="route('resume-profile.show')"
                        class="premium-button-primary"
                    >
                        Set up resume
                    </Link>
                </EmptyState>

                <EmptyState
                    v-else-if="matchedJobs.length === 0"
                    title="No matched jobs yet"
                    description="Your resume is ready, but there are no leads with detected overlap right now. Add more job sources or refine the resume text."
                >
                    <Link
                        :href="route('job-leads.import.entry')"
                        class="premium-button-secondary"
                    >
                        Add job source
                    </Link>
                    <Link
                        :href="route('job-leads.import.entry')"
                        class="premium-button-primary"
                    >
                        Import from URL
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
                                        Matched keywords
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
                                        Missing keywords
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
                                        No obvious keyword gaps right now
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
                                    Go to job
                                </a>
                                <Link
                                    :href="route('job-leads.edit', jobLead.id)"
                                    class="premium-button-secondary"
                                >
                                    Review match
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
