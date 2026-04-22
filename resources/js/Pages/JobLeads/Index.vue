<script setup>
import JobLeadImportForm from '@/Components/JobLeadImportForm.vue';
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
    jobLeads: {
        type: Object,
        required: true,
    },
    leadStatuses: {
        type: Array,
        required: true,
    },
});

const filterForm = reactive({
    lead_status: props.filters.lead_status || '',
    search: props.filters.search || '',
    minimum_relevance_score: props.filters.minimum_relevance_score || '',
});

const leadStatusClasses = {
    saved: 'border-slateglass-300/20 bg-slateglass-300/10 text-slateglass-200',
    shortlisted: 'border-gold-400/20 bg-gold-400/10 text-gold-300',
    applied: 'border-gold-300/30 bg-gold-300/12 text-gold-200',
    ignored: 'border-red-400/20 bg-red-400/10 text-red-200',
};

function submitFilters() {
    router.get(route('job-leads.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.lead_status = '';
    filterForm.search = '';
    filterForm.minimum_relevance_score = '';
    submitFilters();
}

function destroyJobLead(id) {
    if (! window.confirm('Delete this job lead?')) {
        return;
    }

    router.delete(route('job-leads.destroy', id), {
        preserveScroll: true,
    });
}

function scoreCardClasses(score) {
    if (score === null) {
        return 'border-white/10';
    }

    if (score >= 85) {
        return 'border-gold-300/35 bg-gradient-to-br from-gold-400/10 via-white/4 to-transparent shadow-[0_20px_60px_-28px_rgba(245,208,104,0.45)]';
    }

    if (score >= 70) {
        return 'border-slateglass-200/20 bg-white/[0.035]';
    }

    return 'border-white/10';
}

function scoreBadgeClasses(score) {
    if (score === null) {
        return 'border-white/10 bg-white/5 text-slateglass-300';
    }

    if (score >= 85) {
        return 'border-gold-300/30 bg-gold-300/12 text-gold-200';
    }

    if (score >= 70) {
        return 'border-slateglass-200/20 bg-slateglass-200/10 text-slateglass-100';
    }

    return 'border-white/10 bg-white/5 text-slateglass-200';
}
</script>

<template>
    <Head title="Job Leads" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                title="Discovery workspace"
                subtitle="Centralize promising roles, preserve source context, and build the foundation for sharper applications later."
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            href="#import-job-lead"
                            class="premium-button-secondary"
                        >
                            Import from URL
                        </a>
                        <Link
                            :href="route('job-leads.create')"
                            class="premium-button-primary"
                        >
                            Add job lead
                        </Link>
                        <Link
                            :href="route('applications.index')"
                            class="premium-button-secondary"
                        >
                            Open application tracker
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <PageHeader
                eyebrow="Core product"
                title="Job leads"
                description="This is the main workspace for discovery-first job search operations. Save opportunities now and optimize applications later."
            >
                <Link
                    :href="route('job-leads.create')"
                    class="premium-button-primary"
                >
                    New lead
                </Link>
            </PageHeader>

            <SectionCard
                id="import-job-lead"
                title="Import from URL"
                description="Capture an external listing now. Future parsing can enrich this lead without changing the import entry point."
            >
                <JobLeadImportForm />
            </SectionCard>

            <SectionCard
                title="Filter discovery flow"
                description="Search by company or role, narrow by status, and raise the score floor when you need the strongest opportunities first."
            >
                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[220px_1fr_220px_auto]">
                    <div>
                        <label for="lead_status" class="premium-input-label">Lead status</label>
                        <select
                            id="lead_status"
                            v-model="filterForm.lead_status"
                            class="mt-2 block w-full"
                        >
                            <option value="">All statuses</option>
                            <option
                                v-for="leadStatus in leadStatuses"
                                :key="leadStatus"
                                :value="leadStatus"
                            >
                                {{ leadStatus }}
                            </option>
                        </select>
                    </div>

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

                    <div>
                        <label for="minimum_relevance_score" class="premium-input-label">Minimum score</label>
                        <input
                            id="minimum_relevance_score"
                            v-model="filterForm.minimum_relevance_score"
                            type="number"
                            min="0"
                            max="100"
                            class="mt-2 block w-full"
                            placeholder="70"
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
                title="Lead inventory"
                description="Saved opportunities with enough context to support future ranking, resume targeting, and application strategy."
                :padded="false"
            >
                <template #actions>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                        {{ jobLeads.data.length }} visible
                    </span>
                </template>

                <EmptyState
                    v-if="jobLeads.data.length === 0"
                    title="No job leads yet"
                    description="Start with the opportunities worth revisiting later. Discovery comes first; applications follow."
                >
                    <Link
                        :href="route('job-leads.create')"
                        class="premium-button-primary"
                    >
                        Add first lead
                    </Link>
                    <a
                        href="#import-job-lead"
                        class="premium-button-secondary"
                    >
                        Import from URL
                    </a>
                </EmptyState>

                <div
                    v-else
                    class="divide-y divide-white/10"
                >
                    <div
                        v-for="jobLead in jobLeads.data"
                        :key="jobLead.id"
                        class="flex flex-col gap-6 border-l-2 px-6 py-6 transition xl:flex-row xl:items-start xl:justify-between"
                        :class="scoreCardClasses(jobLead.relevance_score)"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-xl font-semibold text-white">
                                    {{ jobLead.company_name }}
                                </h3>
                                <span
                                    class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="leadStatusClasses[jobLead.lead_status] ?? leadStatusClasses.saved"
                                >
                                    {{ jobLead.lead_status }}
                                </span>
                                <span
                                    class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="scoreBadgeClasses(jobLead.relevance_score)"
                                >
                                    {{ jobLead.relevance_score === null ? 'Unscored' : `Score ${jobLead.relevance_score}` }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-slateglass-300">
                                {{ jobLead.job_title }}
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium uppercase tracking-[0.2em] text-slateglass-400">
                                <span v-if="jobLead.source_name">
                                    Source {{ jobLead.source_name }}
                                </span>
                                <span v-if="jobLead.location">
                                    {{ jobLead.location }}
                                </span>
                                <span v-if="jobLead.work_mode">
                                    {{ jobLead.work_mode }}
                                </span>
                                <span v-if="jobLead.salary_range">
                                    {{ jobLead.salary_range }}
                                </span>
                                <span>
                                    Discovered {{ jobLead.discovered_at || 'Not set' }}
                                </span>
                            </div>

                            <p
                                v-if="jobLead.description_excerpt"
                                class="mt-4 max-w-3xl text-sm leading-6 text-slateglass-400"
                            >
                                {{ jobLead.description_excerpt }}
                            </p>

                            <div class="mt-5 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                                <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold-300/80">
                                            Extracted keywords
                                        </p>
                                        <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                                            {{ jobLead.extracted_keywords.length }} found
                                        </span>
                                    </div>

                                    <p
                                        v-if="!jobLead.description_text"
                                        class="mt-4 text-sm text-slateglass-400"
                                    >
                                        Add a job description to unlock ATS insights
                                    </p>

                                    <p
                                        v-else-if="jobLead.extracted_keywords.length === 0"
                                        class="mt-4 text-sm text-slateglass-400"
                                    >
                                        No keywords extracted yet
                                    </p>

                                    <div
                                        v-else
                                        class="mt-4 flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="keyword in jobLead.extracted_keywords"
                                            :key="keyword"
                                            class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
                                        >
                                            {{ keyword }}
                                        </span>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold-300/80">
                                        ATS hints
                                    </p>

                                    <p
                                        v-if="!jobLead.description_text"
                                        class="mt-4 text-sm text-slateglass-400"
                                    >
                                        Add a job description to unlock ATS insights
                                    </p>

                                    <ul
                                        v-else
                                        class="mt-4 space-y-3"
                                    >
                                        <li
                                            v-for="hint in jobLead.ats_hints"
                                            :key="hint"
                                            class="flex items-start gap-3 rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm leading-6 text-slateglass-200"
                                        >
                                            <span class="mt-2 h-1.5 w-1.5 rounded-full bg-gold-300" />
                                            <span>{{ hint }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <a
                                :href="jobLead.source_url"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-4 inline-flex text-sm font-medium text-gold-300 transition hover:text-gold-200"
                            >
                                Open source listing
                            </a>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                :href="route('job-leads.edit', jobLead.id)"
                                class="premium-button-secondary"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="premium-button-danger"
                                @click="destroyJobLead(jobLead.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="jobLeads.links.length > 3"
                    class="flex flex-wrap gap-2 border-t border-white/10 px-6 py-5"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in jobLeads.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        v-html="link.label"
                        class="rounded-2xl px-4 py-2 text-sm font-medium"
                        :class="link.active
                            ? 'bg-gold-400/15 text-gold-300'
                            : 'border border-white/10 bg-white/5 text-slateglass-300 hover:bg-white/10 hover:text-white'"
                    />
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
