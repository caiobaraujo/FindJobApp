<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import MatchWhyDrawer from '@/Components/MatchWhyDrawer.vue';
import ResumeSkillsCard from '@/Components/ResumeSkillsCard.vue';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    analysisStates: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    detectedResumeSkills: {
        type: Array,
        required: true,
    },
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    leadStatuses: {
        type: Array,
        required: true,
    },
    leadStatusCounts: {
        type: Object,
        required: true,
    },
    leadsMissingAnalysisCount: {
        type: Number,
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
    workModes: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();

const filterForm = reactive({
    analysis_state: props.filters.analysis_state || '',
    lead_status: props.filters.lead_status || '',
    search: props.filters.search || '',
    show_ignored: Boolean(props.filters.show_ignored),
    work_mode: props.filters.work_mode || '',
});

const leadStatusActions = ['saved', 'shortlisted', 'applied', 'ignored'];
const leadStatusUpdates = reactive({});

function workspaceRoute() {
    if (route().current('job-leads.index')) {
        return route('job-leads.index');
    }

    return route('matched-jobs.index');
}

function submitFilters() {
    router.get(workspaceRoute(), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.analysis_state = '';
    filterForm.lead_status = '';
    filterForm.search = '';
    filterForm.show_ignored = false;
    filterForm.work_mode = '';
    submitFilters();
}

function leadStatusLabel(leadStatus) {
    return t(`job_lead_form.statuses.${leadStatus}`, leadStatus);
}

function workModeLabel(workMode) {
    return t(`job_lead_form.work_modes.${workMode}`, workMode);
}

function analysisStateLabel(analysisState) {
    return t(`matched_jobs.analysis_states.${analysisState}`, analysisState);
}

function analysisStateFor(jobLead) {
    return jobLead.job_keywords_used.length > 0
        ? 'analyzed'
        : 'missing';
}

function matchQualityFor(jobLead) {
    const matchedCount = jobLead.matched_keywords.length;
    const missingCount = jobLead.missing_keywords.length;
    const totalCount = matchedCount + missingCount;

    if (totalCount === 0) {
        return 'missing';
    }

    const matchRatio = matchedCount / totalCount;

    if (matchedCount >= 4 && matchRatio >= 0.8) {
        return 'strong';
    }

    if (matchedCount >= 2 && matchRatio >= 0.5) {
        return 'good';
    }

    if (matchedCount >= 1) {
        return 'fair';
    }

    return 'missing';
}

function matchQualityLabel(jobLead) {
    return t(`matched_jobs.match_quality.${matchQualityFor(jobLead)}`, 'Match quality');
}

function visibleMatchedKeywords(jobLead) {
    return jobLead.matched_keywords.slice(0, 5);
}

function hiddenMatchedKeywordCount(jobLead) {
    return Math.max(0, jobLead.matched_keywords.length - 5);
}

function leadStatusActionClasses(jobLead, leadStatus) {
    if (jobLead.lead_status === leadStatus) {
        return 'border-gold-300/20 bg-gold-300/10 text-gold-100';
    }

    return 'border-white/10 bg-black/20 text-slateglass-300 hover:border-white/20 hover:bg-white/10 hover:text-white';
}

function setLeadStatus(jobLead, leadStatus) {
    if (jobLead.lead_status === leadStatus || leadStatusUpdates[jobLead.id]) {
        return;
    }

    leadStatusUpdates[jobLead.id] = leadStatus;

    router.patch(route('job-leads.update', jobLead.id), {
        lead_status: leadStatus,
        stay_on_page: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            delete leadStatusUpdates[jobLead.id];
        },
    });
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
                            {{ t('buttons.add_job', 'Add job') }}
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <ResumeSkillsCard :skills="detectedResumeSkills" />

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
                    {{ t('buttons.add_job', 'Add job') }}
                </Link>
            </PageHeader>

            <SectionCard
                :title="t('matched_jobs.filter_title', 'Find a match faster')"
                :description="t('matched_jobs.filter_description', 'Search by company or role. The list is already narrowed to jobs with at least one detected match when your resume is ready.')"
            >
                <div class="mb-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-3xl border border-white/10 bg-black/20 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                            {{ t('matched_jobs.active_leads', 'Active leads') }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-white">
                            {{ leadStatusCounts.active }}
                        </p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-black/20 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                            {{ t('matched_jobs.ignored_leads', 'Ignored leads') }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-white">
                            {{ leadStatusCounts.ignored }}
                        </p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-black/20 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                            {{ t('matched_jobs.applied_leads', 'Applied leads') }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-white">
                            {{ leadStatusCounts.applied }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="leadsMissingAnalysisCount > 0"
                    class="mb-5 rounded-3xl border border-gold-300/15 bg-gold-300/[0.06] px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    {{ leadsMissingAnalysisCount === 1
                        ? t('matched_jobs.missing_analysis_single', '1 saved lead does not have keyword analysis yet.')
                        : t('matched_jobs.missing_analysis_multiple', ':count saved leads do not have keyword analysis yet.').replace(':count', String(leadsMissingAnalysisCount)) }}
                    <Link
                        :href="route('job-leads.create')"
                        class="ml-2 font-semibold text-gold-200 underline decoration-gold-300/40 underline-offset-4"
                    >
                        {{ t('matched_jobs.paste_job_text', 'Paste job text on intake') }}
                    </Link>
                </div>

                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_repeat(3,minmax(0,180px))]">
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

                    <div>
                        <label for="lead_status" class="premium-input-label">{{ t('matched_jobs.lead_status_filter', 'Lead status') }}</label>
                        <select
                            id="lead_status"
                            v-model="filterForm.lead_status"
                            class="mt-2 block w-full"
                        >
                            <option value="">{{ t('matched_jobs.all_lead_statuses', 'All statuses') }}</option>
                            <option
                                v-for="leadStatus in leadStatuses"
                                :key="leadStatus"
                                :value="leadStatus"
                            >
                                {{ leadStatusLabel(leadStatus) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="analysis_state" class="premium-input-label">{{ t('matched_jobs.analysis_state_filter', 'Analysis state') }}</label>
                        <select
                            id="analysis_state"
                            v-model="filterForm.analysis_state"
                            class="mt-2 block w-full"
                        >
                            <option value="">{{ t('matched_jobs.all_analysis_states', 'All analysis states') }}</option>
                            <option
                                v-for="analysisState in analysisStates"
                                :key="analysisState"
                                :value="analysisState"
                            >
                                {{ analysisStateLabel(analysisState) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="work_mode" class="premium-input-label">{{ t('matched_jobs.work_mode_filter', 'Work mode') }}</label>
                        <select
                            id="work_mode"
                            v-model="filterForm.work_mode"
                            class="mt-2 block w-full"
                        >
                            <option value="">{{ t('matched_jobs.all_work_modes', 'All work modes') }}</option>
                            <option
                                v-for="workMode in workModes"
                                :key="workMode"
                                :value="workMode"
                            >
                                {{ workModeLabel(workMode) }}
                            </option>
                        </select>
                    </div>

                    <div class="xl:col-span-4 flex flex-wrap items-center justify-between gap-3">
                        <label class="flex items-center gap-3 text-sm text-slateglass-300">
                            <input
                                v-model="filterForm.show_ignored"
                                type="checkbox"
                                class="h-4 w-4 rounded border-white/20 bg-black/20 text-gold-300 focus:ring-gold-300/40"
                            >
                            <span>{{ t('matched_jobs.show_ignored', 'Show ignored leads') }}</span>
                        </label>

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
                        ? t('matched_jobs.empty_resume_needs_text_description', 'Your resume file is saved, but matching still needs extracted or pasted resume text. TXT, PDF, and DOCX can extract locally when readable; for DOC or failed extraction, paste resume text or add core skills first.')
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
                        {{ t('buttons.add_job', 'Add job') }}
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
                                <span
                                    v-if="jobLead.source_name"
                                    class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-300"
                                >
                                    {{ jobLead.source_name }}
                                </span>
                                <span
                                    v-if="jobLead.source_host"
                                    class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400"
                                >
                                    {{ jobLead.source_host }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-slateglass-300">
                                {{ jobLead.job_title }}
                            </p>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-300"
                                >
                                    {{ leadStatusLabel(jobLead.lead_status) }}
                                </span>
                                <span
                                    v-if="jobLead.work_mode"
                                    class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400"
                                >
                                    {{ workModeLabel(jobLead.work_mode) }}
                                </span>
                                <span
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="analysisStateFor(jobLead) === 'analyzed'
                                        ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                                        : 'border-gold-300/20 bg-gold-300/10 text-gold-200'"
                                >
                                    {{ analysisStateLabel(analysisStateFor(jobLead)) }}
                                </span>
                                <span
                                    v-if="jobLead.has_limited_analysis"
                                    class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-gold-200"
                                >
                                    {{ t('matched_jobs.limited_analysis', 'Limited analysis') }}
                                </span>
                                <span
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="matchQualityFor(jobLead) === 'strong'
                                        ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                                        : matchQualityFor(jobLead) === 'good'
                                            ? 'border-gold-300/20 bg-gold-300/10 text-gold-200'
                                            : matchQualityFor(jobLead) === 'fair'
                                                ? 'border-white/10 bg-white/5 text-slateglass-200'
                                                : 'border-red-400/20 bg-red-400/10 text-red-200'"
                                >
                                    {{ matchQualityLabel(jobLead) }}
                                </span>
                            </div>
                            <div class="mt-6 grid gap-4 xl:grid-cols-2">
                                <div class="rounded-3xl border border-emerald-400/12 bg-emerald-400/[0.05] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/90">
                                        {{ t('matched_jobs.matched_keywords', 'Matched keywords') }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span
                                            v-for="keyword in visibleMatchedKeywords(jobLead)"
                                            :key="keyword"
                                            class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200"
                                        >
                                            {{ keyword }}
                                        </span>
                                        <span
                                            v-if="hiddenMatchedKeywordCount(jobLead) > 0"
                                            class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-medium text-slateglass-300"
                                        >
                                            +{{ hiddenMatchedKeywordCount(jobLead) }} {{ t('matched_jobs.more_keywords', 'more') }}
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
                                <Link
                                    v-if="jobLead.has_limited_analysis"
                                    :href="`${route('job-leads.edit', jobLead.id)}?focus=description#job-description`"
                                    class="premium-button-secondary"
                                >
                                    {{ t('matched_jobs.add_description', 'Add description') }}
                                </Link>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                                    {{ t('matched_jobs.quick_actions', 'Quick actions') }}
                                </span>
                                <button
                                    v-for="leadStatus in leadStatusActions"
                                    :key="leadStatus"
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] transition"
                                    :class="leadStatusActionClasses(jobLead, leadStatus)"
                                    :disabled="Boolean(leadStatusUpdates[jobLead.id])"
                                    :aria-pressed="jobLead.lead_status === leadStatus"
                                    @click="setLeadStatus(jobLead, leadStatus)"
                                >
                                    {{ leadStatusLabel(leadStatus) }}
                                </button>
                            </div>

                            <div
                                v-if="jobLead.has_limited_analysis"
                                class="mt-5 rounded-3xl border border-gold-300/15 bg-gold-300/[0.06] px-4 py-3 text-sm text-slateglass-200"
                            >
                                <p class="font-semibold text-gold-200">
                                    {{ t('matched_jobs.limited_analysis', 'Limited analysis') }}
                                </p>
                                <p class="mt-1">
                                    {{ t('matched_jobs.analysis_unavailable', 'Add a job description to improve matching. Keyword analysis is not available yet for this lead.') }}
                                </p>
                            </div>

                            <MatchWhyDrawer
                                v-if="jobLead.can_explain_match"
                                :resume-skills="jobLead.resume_skills_used"
                                :job-keywords="jobLead.job_keywords_used"
                                :matched-keywords="jobLead.matched_keywords"
                                :missing-keywords="jobLead.missing_keywords"
                            />
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
