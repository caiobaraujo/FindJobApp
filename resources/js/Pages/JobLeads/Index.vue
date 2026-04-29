<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import MatchWhyDrawer from '@/Components/MatchWhyDrawer.vue';
import InputError from '@/Components/InputError.vue';
import { useI18n } from '@/composables/useI18n';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    analysisReadinessOptions: {
        type: Array,
        required: true,
    },
    analysisStates: {
        type: Array,
        required: true,
    },
    discoveryStatus: {
        type: Object,
        default: null,
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
    isLatestDiscoveryView: {
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
    latestDiscoveryMatchFunnel: {
        type: Object,
        default: null,
    },
    latestDiscoveryWorkspaceSplit: {
        type: Object,
        default: null,
    },
    matchedJobsVisibilitySummary: {
        type: Object,
        default: null,
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
    workspaceView: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
const page = usePage();
const discoveryResults = computed(() => Array.isArray(page.props.flash?.discovery) ? page.props.flash.discovery : []);
const discoveryBatchId = computed(() => page.props.flash?.discovery_batch_id || '');
const discoveryCreatedCount = computed(() => Number(page.props.flash?.discovery_created_count || 0));
const discoverySearchQuery = computed(() => page.props.flash?.discovery_search_query || '');
const discoveryForm = useForm({
    search_query: discoverySearchQuery.value,
});

watch(discoverySearchQuery, (value) => {
    discoveryForm.search_query = value || '';
});

const filterForm = reactive({
    discovery_batch: props.filters.discovery_batch || '',
    lead_group: props.filters.lead_group || 'matched',
    location_scope: props.filters.location_scope || 'brazil',
    search: props.filters.search || '',
    work_mode: props.filters.work_mode || '',
});

const defaultWorkspaceFilters = Object.freeze({
    lead_group: 'all',
    location_scope: 'brazil',
    search: '',
    work_mode: '',
});

const leadStatusActions = ['saved', 'shortlisted', 'applied', 'ignored'];
const leadStatusUpdates = reactive({});

function workspaceRoute() {
    return route(routeNameForWorkspaceView(filterForm.lead_group));
}

function submitFilters() {
    router.get(workspaceRoute(), paramsForWorkspaceView(filterForm.lead_group), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function runDiscovery() {
    if (discoveryForm.processing) {
        return;
    }

    discoveryForm.post(route('job-leads.discover'), {
        preserveScroll: true,
    });
}

function resetFilters() {
    filterForm.discovery_batch = '';
    filterForm.lead_group = props.workspaceView;
    filterForm.location_scope = defaultWorkspaceFilters.location_scope;
    filterForm.search = defaultWorkspaceFilters.search;
    filterForm.work_mode = defaultWorkspaceFilters.work_mode;
    submitFilters();
}

function routeNameForWorkspaceView(workspaceView) {
    return workspaceView === 'matched' ? 'matched-jobs.index' : 'job-leads.index';
}

function paramsForWorkspaceView(workspaceView, discoveryBatch = '', locationScope = filterForm.location_scope) {
    const params = {
        ...filterForm,
        location_scope: locationScope,
    };

    if (workspaceView === 'matched') {
        delete params.lead_group;
    } else {
        params.lead_group = workspaceView === 'unmatched' ? 'unmatched' : 'all';
    }

    delete params.discovery_batch;

    if (discoveryBatch === '') {
        return params;
    }

    return {
        ...params,
        discovery_batch: discoveryBatch,
    };
}

function workspaceHrefForView(workspaceView, discoveryBatch = '', locationScope = filterForm.location_scope) {
    return route(
        routeNameForWorkspaceView(workspaceView),
        paramsForWorkspaceView(workspaceView, discoveryBatch, locationScope),
    );
}

function workspaceHref(discoveryBatch = '', locationScope = filterForm.location_scope) {
    return workspaceHrefForView(props.workspaceView, discoveryBatch, locationScope);
}

function normalWorkspaceHref() {
    return workspaceHrefForView(props.workspaceView);
}

function latestDiscoveryParams() {
    return {
        discovery_batch: 'latest',
        location_scope: 'all',
        search: '',
        work_mode: '',
    };
}

function leadStatusLabel(leadStatus) {
    return t(`job_lead_form.statuses.${leadStatus}`, leadStatus);
}

function workModeLabel(workMode) {
    return t(`job_lead_form.work_modes.${workMode}`, workMode);
}

function locationClassificationLabel(locationClassification) {
    if (locationClassification === 'brazil') {
        return t('matched_jobs.brazil_job', 'Brazil');
    }

    if (locationClassification === 'international') {
        return t('matched_jobs.international_job', 'International');
    }

    return null;
}

function locationClassificationClasses(locationClassification) {
    if (locationClassification === 'brazil') {
        return 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200';
    }

    if (locationClassification === 'international') {
        return 'border-sky-400/20 bg-sky-400/10 text-sky-200';
    }

    return '';
}

function discoverySourceLabel(source) {
    return t(`job_discovery.sources.${source.replaceAll('-', '_')}`, source);
}

const discoverySummary = computed(() => discoveryResults.value.reduce((summary, sourceResult) => ({
    fetched: summary.fetched + (sourceResult.fetched || 0),
    created: summary.created + (sourceResult.created || 0),
    duplicates: summary.duplicates + (sourceResult.duplicates || 0),
    skipped_not_matching_query: summary.skipped_not_matching_query + (sourceResult.skipped_not_matching_query || 0),
    failed: summary.failed + (sourceResult.failed || 0),
    query_used: summary.query_used || Boolean(sourceResult.query_used),
}), {
    fetched: 0,
    created: 0,
    duplicates: 0,
    skipped_not_matching_query: 0,
    failed: 0,
    query_used: false,
}));

const viewingLatestDiscoveryBatch = computed(() => props.isLatestDiscoveryView);
const latestDiscoveryHref = computed(() => route(
    routeNameForWorkspaceView(props.workspaceView),
    {
        ...latestDiscoveryParams(),
        ...(props.workspaceView === 'matched' ? {} : {
            lead_group: props.workspaceView === 'unmatched' ? 'unmatched' : 'all',
        }),
    },
));
const allJobsHref = computed(() => workspaceHrefForView('all'));
const hiddenDiscoveryResults = computed(() => discoveryCreatedCount.value > 0 && props.matchedJobs.length === 0 && ! viewingLatestDiscoveryBatch.value);
const matchedJobsVisibilitySummary = computed(() => props.matchedJobsVisibilitySummary || {
    visible_count: props.matchedJobs.length,
    hidden_ignored_count: 0,
    hidden_international_count: 0,
    total_count: props.matchedJobs.length,
});
const latestDiscoveryMatchFunnel = computed(() => props.latestDiscoveryMatchFunnel);
const latestDiscoveryWorkspaceSplit = computed(() => props.latestDiscoveryWorkspaceSplit);
const isMatchedWorkspace = computed(() => props.workspaceView === 'matched');
const isAllDiscoveredWorkspace = computed(() => props.workspaceView === 'all');
const isUnmatchedWorkspace = computed(() => props.workspaceView === 'unmatched');

const pageTitle = computed(() => {
    if (isMatchedWorkspace.value) {
        return t('nav.matched_jobs', 'Matched Jobs');
    }

    if (isUnmatchedWorkspace.value) {
        return t('job_leads.unmatched_title', 'Broader IT Leads');
    }

    return t('nav.job_leads', 'Job Leads');
});

const workspaceTitle = computed(() => {
    if (isMatchedWorkspace.value) {
        return t('matched_jobs.title', 'Matched jobs');
    }

    if (isUnmatchedWorkspace.value) {
        return t('job_leads.unmatched_title', 'Broader IT leads');
    }

    return t('job_leads.all_discovered_title', 'Job leads');
});

const workspaceSubtitle = computed(() => {
    if (isMatchedWorkspace.value) {
        return t('matched_jobs.subtitle', 'See jobs that overlap with your resume, review matched and missing keywords, and go straight to the source listing.');
    }

    if (isUnmatchedWorkspace.value) {
        return t('job_leads.unmatched_subtitle', 'Inspect discovered technology opportunities that do not currently overlap with your resume, but may still be worth evaluating later.');
    }

    return t('job_leads.all_discovered_subtitle', 'Review both resume-matched leads and broader technology opportunities discovered for your workspace.');
});

const filterTitle = computed(() => t('job_leads.filter_title', 'Refine discovered leads'));

const filterDescription = computed(() => {
    return t(
        'job_leads.filter_description',
        'This section only filters existing JobLead records in your workspace. It does not run discovery again.',
    );
});

const resultsTitle = computed(() => isMatchedWorkspace.value
    ? t('matched_jobs.results_title', 'Matching results')
    : isUnmatchedWorkspace.value
        ? t('job_leads.unmatched_results_title', 'Broader IT results')
        : t('job_leads.results_title', 'Discovered lead results'));

const resultsDescription = computed(() => {
    if (isMatchedWorkspace.value) {
        return t('matched_jobs.results_description', 'Job cards are simplified to the signals that matter most right now: overlap, gaps, and direct source access.');
    }

    if (isUnmatchedWorkspace.value) {
        return t('job_leads.unmatched_results_description', 'These leads look like technology opportunities, but they do not currently overlap with your resume signals.');
    }

    return t('job_leads.results_description', 'This workspace includes both resume-matched leads and broader technology opportunities discovered from deterministic sources.');
});

function resumeOverlapState(jobLead) {
    return jobLead.matched_keywords.length > 0 ? 'matched' : 'broader';
}

function discoveryPrimaryMessage(summary) {
    if (discoveryCreatedCount.value > 0) {
        return t(
            discoveryCreatedCount.value === 1
                ? 'job_discovery.new_jobs_found_single'
                : 'job_discovery.new_jobs_found_multiple',
            discoveryCreatedCount.value === 1
                ? '1 new job found.'
                : ':count new jobs found.',
            {
            count: discoveryCreatedCount.value,
            },
        );
    }

    return t('job_discovery.no_new_jobs_found', 'No new jobs found.');
}

function discoverySecondaryMessages(summary) {
    const messages = [];

    if (discoveryCreatedCount.value > 0) {
        messages.push(t(
            'job_discovery.new_jobs_note',
            'The new jobs appear below, prioritized by your resume.',
        ));

        return messages;
    }

    messages.push(t(
        'job_discovery.checked_configured_sources',
        'We checked the configured sources.',
    ));

    if (summary.duplicates > 0) {
        messages.push(t(
            'job_discovery.result_no_new_duplicates',
            'No new jobs. The jobs found were already in your workspace.',
        ));
    }

    if (summary.skipped_not_matching_query > 0) {
        messages.push(t(
            'job_discovery.query_skipped_summary',
            ':count jobs were skipped because they did not match your search.',
            { count: summary.skipped_not_matching_query },
        ));
    }

    if (summary.failed > 0) {
        messages.push(t(
            'job_discovery.result_failed_only',
            'Some sources failed. Try again later.',
        ));
    }

    return messages;
}

function discoveryDetailsRow(sourceResult) {
    const profileDetails = Array.isArray(sourceResult.query_profile_keys) && sourceResult.query_profile_keys.length > 0
        ? ` · profiles ${sourceResult.query_profile_keys.join(', ')} · profile-created ${sourceResult.created_by_query_profiles || 0}`
        : '';

    return t(
        'job_discovery.details_row',
        ':source — found :fetched · new :created · duplicates :duplicates · hidden :hidden · limited :limited · missing description :missingDescription · missing keywords :missingKeywords · skipped :skipped · failed :failed:profileDetails',
        {
            source: discoverySourceLabel(sourceResult.source),
            fetched: sourceResult.fetched || 0,
            created: sourceResult.created || 0,
            duplicates: sourceResult.duplicates || 0,
            hidden: sourceResult.hidden_by_default || 0,
            limited: sourceResult.limited_analysis || 0,
            missingDescription: sourceResult.missing_description || 0,
            missingKeywords: sourceResult.missing_keywords || 0,
            skipped: sourceResult.skipped_not_matching_query || 0,
            failed: sourceResult.failed || 0,
            profileDetails,
        },
    );
}

function discoveryTargetDetails(sourceResult) {
    if (!Array.isArray(sourceResult.target_diagnostics)) {
        return [];
    }

    return sourceResult.target_diagnostics.map((targetSummary) => {
        const targetName = targetSummary.target_name || targetSummary.target_identifier || 'Target';
        const platform = targetSummary.platform || targetName;

        return `${targetName} (${platform}) — fetched ${targetSummary.fetched_candidates || 0} · matched ${targetSummary.matched_candidates || 0} · imported ${targetSummary.imported || 0} · duplicates ${targetSummary.deduplicated || 0} · skipped ${targetSummary.skipped_by_query || 0} · expired ${targetSummary.skipped_expired || 0} · missing company ${targetSummary.skipped_missing_company || 0} · failed ${targetSummary.failed || 0}`;
    });
}

function analysisStateFor(jobLead) {
    return jobLead.job_keywords_used.length > 0
        ? 'analyzed'
        : 'missing';
}

function analysisStateLabel(analysisState) {
    return t(`matched_jobs.analysis_states.${analysisState}`, analysisState);
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

function preferenceFitLabel(preferenceFit) {
    if (!preferenceFit?.status) {
        return null;
    }

    return t(`matched_jobs.preference_fit.${preferenceFit.status}`, preferenceFit.status);
}

function preferenceFitReasons(jobLead) {
    const preferenceFit = jobLead.preference_fit;

    if (!preferenceFit) {
        return [];
    }

    const matchedReasons = (preferenceFit.matched || []).map((reason) => ({
        key: `${reason}-match`,
        label: t(`matched_jobs.preference_fit.reasons.${reason}_match`, reason),
        tone: 'match',
    }));

    const mismatchedReasons = (preferenceFit.mismatched || []).map((reason) => ({
        key: `${reason}-mismatch`,
        label: t(`matched_jobs.preference_fit.reasons.${reason}_mismatch`, reason),
        tone: 'mismatch',
    }));

    return [...matchedReasons, ...mismatchedReasons].slice(0, 2);
}

function preferenceFitClasses(preferenceFit) {
    if (!preferenceFit?.status) {
        return '';
    }

    if (preferenceFit.status === 'match') {
        return 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200';
    }

    if (preferenceFit.status === 'partial') {
        return 'border-gold-300/20 bg-gold-300/10 text-gold-200';
    }

    return 'border-red-400/20 bg-red-400/10 text-red-200';
}

function whyThisJobPreferenceLabel(whyThisJob) {
    if (whyThisJob?.preference_summary === 'match') {
        return t('matched_jobs.matches_preferences', 'Matches your preferences');
    }

    if (whyThisJob?.preference_summary === 'partial') {
        return t('matched_jobs.partially_matches_preferences', 'Partially matches your preferences');
    }

    return null;
}

function keywordSummary(keywords) {
    return keywords.join(', ');
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
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell
                :title="workspaceTitle"
                :subtitle="workspaceSubtitle"
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
            <div class="mb-8 rounded-[2rem] border border-gold-300/15 bg-[radial-gradient(circle_at_top_left,rgba(255,215,140,0.14),transparent_42%),linear-gradient(180deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02))] px-6 py-6 shadow-panel sm:px-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-gold-300/80">
                            {{ t('job_discovery.search_new_jobs', 'Search new jobs') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-semibold text-white sm:text-3xl">
                            {{ t('job_discovery.search_new_jobs_headline', 'Run discovery from a simple job search') }}
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slateglass-300">
                            {{ t('job_discovery.search_new_jobs_description', 'Type a natural query and FindJobApp will run deterministic discovery across the configured sources. Examples: “python remote brazil”, “javascript or laravel”, “django backend remote”.') }}
                        </p>
                    </div>
                    <Link
                        :href="route('job-leads.create')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.add_job', 'Add job') }}
                    </Link>
                </div>

                <div class="mt-6">
                    <form
                        class="space-y-3"
                        @submit.prevent="runDiscovery"
                    >
                        <div>
                            <label
                                for="discovery-search-query"
                                class="text-sm font-medium text-white"
                            >
                                {{ t('job_discovery.search_new_jobs', 'Search new jobs') }}
                            </label>
                            <input
                                id="discovery-search-query"
                                v-model="discoveryForm.search_query"
                                data-testid="discovery-search-input"
                                type="text"
                                class="mt-2 block w-full rounded-3xl border border-gold-300/20 bg-black/20 px-5 py-4 text-base text-white placeholder:text-slateglass-400 focus:border-gold-300/50 focus:outline-none focus:ring-2 focus:ring-gold-300/20"
                                :placeholder="t('job_discovery.search_new_jobs_placeholder', 'python remote brazil · javascript or laravel · django backend remote')"
                            >
                            <InputError
                                class="mt-2"
                                :message="discoveryForm.errors.search_query"
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                data-testid="find-jobs-button"
                                class="premium-button-primary justify-center disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="discoveryForm.processing"
                            >
                                {{ discoveryForm.processing
                                    ? t('job_discovery.finding_jobs', 'Finding jobs...')
                                    : t('job_discovery.find_jobs', 'Find jobs') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <SectionCard
                :title="filterTitle"
                :description="filterDescription"
            >
                <div
                    v-if="discoveryResults.length"
                    data-testid="discovery-result"
                    class="mb-5 rounded-3xl border border-white/10 bg-black/20 px-5 py-4"
                >
                    <p class="text-sm font-semibold text-white">
                        {{ discoveryPrimaryMessage(discoverySummary) }}
                    </p>
                    <div
                        v-if="discoveryCreatedCount > 0 && discoveryBatchId"
                        class="mt-3 flex flex-wrap gap-3"
                    >
                        <Link
                            :href="latestDiscoveryHref"
                            data-testid="view-new-jobs-link"
                            class="premium-button-primary"
                        >
                            {{ t('job_discovery.view_new_jobs', 'View new jobs') }}
                        </Link>
                        <Link
                            :href="allJobsHref"
                            class="premium-button-secondary"
                        >
                            {{ t('job_discovery.view_all_jobs', 'View all jobs') }}
                        </Link>
                    </div>
                    <p
                        v-for="message in discoverySecondaryMessages(discoverySummary)"
                        :key="message"
                        class="mt-2 text-sm leading-6 text-slateglass-300"
                    >
                        {{ message }}
                    </p>
                    <details class="mt-3 rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-slateglass-300">
                        <summary class="cursor-pointer list-none font-medium text-white">
                            {{ t('job_discovery.technical_details', 'Technical details') }}
                        </summary>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="sourceResult in discoveryResults"
                                :key="sourceResult.source"
                                class="space-y-1 text-xs leading-6 text-slateglass-300"
                            >
                                <p>{{ discoveryDetailsRow(sourceResult) }}</p>
                                <p
                                    v-for="targetLine in discoveryTargetDetails(sourceResult)"
                                    :key="`${sourceResult.source}-${targetLine}`"
                                    class="pl-3 text-[11px] text-slateglass-400"
                                >
                                    {{ targetLine }}
                                </p>
                            </div>
                        </div>
                    </details>
                </div>

                <div
                    v-if="viewingLatestDiscoveryBatch"
                    class="mb-5 rounded-3xl border border-gold-300/15 bg-gold-300/[0.06] px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    <p>
                        {{ t('job_discovery.showing_all_jobs_found_in_last_search', 'Showing all jobs found in your last search.') }}
                    </p>
                    <p class="mt-1 text-slateglass-300">
                        {{ t('job_discovery.latest_search_ignores_filters', 'This view ignores filters so you can review all results.') }}
                    </p>
                    <Link
                        :href="allJobsHref"
                        class="ml-2 font-semibold text-gold-200 underline decoration-gold-300/40 underline-offset-4"
                    >
                        {{ t('job_discovery.back_to_normal_view', 'Back to normal view') }}
                    </Link>
                </div>

                <div
                    v-if="hiddenDiscoveryResults"
                    class="mb-5 rounded-3xl border border-amber-400/20 bg-amber-400/10 px-5 py-4 text-sm leading-7 text-amber-100"
                >
                    <p>
                        {{ t('job_discovery.new_jobs_hidden_by_filters', 'New jobs were found but are hidden by your filters.') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="premium-button-primary"
                            @click="resetFilters"
                        >
                            {{ t('matched_jobs.reset', 'Reset') }}
                        </button>
                        <Link
                            v-if="discoveryBatchId"
                            :href="latestDiscoveryHref"
                            class="premium-button-secondary"
                        >
                            {{ t('job_discovery.view_new_jobs', 'View new jobs') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="leadsMissingAnalysisCount > 0"
                    class="mb-5 rounded-3xl border border-gold-300/15 bg-gold-300/[0.06] px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    {{ leadsMissingAnalysisCount === 1
                        ? t('matched_jobs.missing_analysis_single', '1 saved lead does not have keyword analysis yet.')
                        : t('matched_jobs.missing_analysis_multiple', ':count saved leads do not have keyword analysis yet.', { count: leadsMissingAnalysisCount }) }}
                    <Link
                        :href="route('job-leads.create')"
                        class="ml-2 font-semibold text-gold-200 underline decoration-gold-300/40 underline-offset-4"
                    >
                        {{ t('matched_jobs.paste_job_text', 'Paste job text on intake') }}
                    </Link>
                </div>

                <form @submit.prevent="submitFilters" class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_repeat(3,minmax(0,180px))]">
                    <div>
                        <label for="lead_group" class="premium-input-label">{{ t('job_leads.lead_group_filter', 'Lead group') }}</label>
                        <select
                            id="lead_group"
                            v-model="filterForm.lead_group"
                            class="mt-2 block w-full"
                        >
                            <option value="matched">{{ t('job_leads.segment_matched', 'Matched leads') }}</option>
                            <option value="all">{{ t('job_leads.segment_all_discovered', 'Job leads') }}</option>
                            <option value="unmatched">{{ t('job_leads.segment_unmatched', 'Broader IT leads') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="search" class="premium-input-label">{{ t('matched_jobs.search', 'Search') }}</label>
                        <input
                            id="search"
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 block w-full"
                            :placeholder="t('job_leads.search_placeholder', 'python remote brazil · javascript or laravel · django backend remote')"
                        >
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

                    <div class="lg:col-span-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-3 text-sm text-slateglass-300">
                                <input
                                    v-model="filterForm.location_scope"
                                    type="checkbox"
                                    true-value="all"
                                    false-value="brazil"
                                    class="h-4 w-4 rounded border-white/20 bg-black/20 text-gold-300 focus:ring-gold-300/40"
                                >
                                <span>{{ t('job_leads.show_international_jobs', 'Show international jobs') }}</span>
                            </label>
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
                    </div>
                </form>
            </SectionCard>

            <SectionCard
                :title="resultsTitle"
                :description="resultsDescription"
                :padded="false"
            >
                <template #actions>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                        {{ matchedJobs.length }} {{ t('matched_jobs.visible', 'visible') }}
                    </span>
                </template>

                <div
                    v-if="latestDiscoveryWorkspaceSplit"
                    class="mx-6 mt-6 rounded-3xl border border-sky-400/15 bg-sky-400/5 px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    <p class="font-semibold text-white">
                        {{ t(
                            'job_leads.latest_discovery_workspace_split_title',
                            'Latest discovery batch: :total discovered, :matched matched, :unmatched broader IT leads.',
                            {
                                total: latestDiscoveryWorkspaceSplit.latest_batch_total_count,
                                matched: latestDiscoveryWorkspaceSplit.matched_leads_count,
                                unmatched: latestDiscoveryWorkspaceSplit.unmatched_leads_count,
                            },
                        ) }}
                    </p>
                    <p class="mt-1 text-slateglass-300">
                        {{ t(
                            'job_leads.latest_discovery_workspace_split_visibility',
                            'Visible now under the current filters: :matched visible matched leads and :unmatched visible broader IT leads. :international hidden outside Brazil-first view.',
                            {
                                matched: latestDiscoveryWorkspaceSplit.visible_matched_count,
                                unmatched: latestDiscoveryWorkspaceSplit.visible_unmatched_count,
                                international: latestDiscoveryWorkspaceSplit.hidden_international_count,
                            },
                        ) }}
                    </p>
                    <p
                        v-if="filterForm.location_scope === 'brazil' && latestDiscoveryWorkspaceSplit.hidden_international_count > 0"
                        class="mt-1 text-xs leading-6 text-sky-200/90"
                    >
                        {{ t('job_leads.latest_discovery_workspace_split_note', 'Enable international leads to review the hidden international portion in both matched and broader views.') }}
                    </p>
                </div>

                <div
                    v-if="isMatchedWorkspace && resumeReady"
                    class="mx-6 mt-4 rounded-3xl border border-white/10 bg-black/20 px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    <p class="font-semibold text-white">
                        {{ t(
                            'matched_jobs.visibility_summary',
                            ':visible visible matched leads. :total total matched leads before default hiding.',
                            {
                                visible: matchedJobsVisibilitySummary.visible_count,
                                total: matchedJobsVisibilitySummary.total_count,
                            },
                        ) }}
                    </p>
                    <p class="mt-1 text-slateglass-300">
                        {{ t(
                            'matched_jobs.visibility_hidden_summary',
                            ':international hidden outside Brazil-first view. :ignored hidden because ignored leads stay off by default.',
                            {
                                international: matchedJobsVisibilitySummary.hidden_international_count,
                                ignored: matchedJobsVisibilitySummary.hidden_ignored_count,
                            },
                        ) }}
                    </p>
                    <p
                        v-if="matchedJobsVisibilitySummary.hidden_international_count > 0 && matchedJobsVisibilitySummary.hidden_ignored_count > 0"
                        class="mt-1 text-xs leading-6 text-slateglass-400"
                    >
                        {{ t('matched_jobs.visibility_overlap_note', 'A matched lead can appear in both hidden groups.') }}
                    </p>
                </div>

                <div
                    v-if="resumeReady && latestDiscoveryMatchFunnel"
                    class="mx-6 mt-4 rounded-3xl border border-emerald-400/15 bg-emerald-400/5 px-5 py-4 text-sm leading-7 text-slateglass-200"
                >
                    <p class="font-semibold text-white">
                        {{ t(
                            'matched_jobs.latest_discovery_funnel_title',
                            'Latest discovery batch: :imported imported, :matched matched before default hiding, :visible visible now.',
                            {
                                imported: latestDiscoveryMatchFunnel.latest_batch_total_count,
                                matched: latestDiscoveryMatchFunnel.matched_before_default_hiding_count,
                                visible: latestDiscoveryMatchFunnel.visible_matched_count,
                            },
                        ) }}
                    </p>
                    <p class="mt-1 text-slateglass-300">
                        {{ t(
                            'matched_jobs.latest_discovery_funnel_hidden',
                            ':notMatched imported but not considered matched. Hidden now: :international international, :ignored ignored, :status status filter, :readiness readiness filter, :analysis analysis state filter, :workMode work mode filter, :search search filter.',
                            {
                                notMatched: latestDiscoveryMatchFunnel.imported_not_matched_count,
                                international: latestDiscoveryMatchFunnel.hidden_international_count,
                                ignored: latestDiscoveryMatchFunnel.hidden_ignored_count,
                                status: latestDiscoveryMatchFunnel.hidden_status_filter_count,
                                readiness: latestDiscoveryMatchFunnel.hidden_analysis_readiness_filter_count,
                                analysis: latestDiscoveryMatchFunnel.hidden_analysis_state_filter_count,
                                workMode: latestDiscoveryMatchFunnel.hidden_work_mode_filter_count,
                                search: latestDiscoveryMatchFunnel.hidden_search_text_filter_count,
                            },
                        ) }}
                    </p>
                    <p
                        v-if="filterForm.location_scope === 'brazil' && latestDiscoveryMatchFunnel.hidden_international_count > 0"
                        class="mt-1 text-xs leading-6 text-emerald-200/90"
                    >
                        {{ t('matched_jobs.latest_discovery_include_international_note', 'Enable “include international jobs” to move eligible international matches from hidden to visible.') }}
                    </p>
                </div>

                <EmptyState
                    v-if="!resumeReady"
                    :title="t('matched_jobs.empty_resume_title', 'Matching starts after resume upload')"
                    :description="isMatchedWorkspace
                        ? (resumeNeedsTextInput
                        ? t('matched_jobs.empty_resume_needs_text_description', 'Your resume file is saved, but matching still needs extracted or pasted resume text. TXT, PDF, and DOCX can extract locally when readable; for DOC or failed extraction, paste resume text or add core skills first.')
                        : t('matched_jobs.empty_resume_description', 'Upload your resume first. Once it is ready, this page will surface only jobs with detected overlap.'))
                        : t('job_leads.empty_resume_broader_description', 'Upload your resume to separate matched leads from broader technology opportunities. All discovered leads remain available in the meantime.')"
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
                    :title="isMatchedWorkspace
                        ? t('matched_jobs.empty_matches_title', 'No matched jobs yet')
                        : isUnmatchedWorkspace
                            ? t('job_leads.empty_unmatched_title', 'No broader IT leads right now')
                            : t('job_leads.empty_all_title', 'No discovered leads right now')"
                    :description="isMatchedWorkspace
                        ? t('matched_jobs.empty_matches_description', 'Your resume is ready, but there are no leads with detected overlap right now.')
                        : isUnmatchedWorkspace
                            ? t('job_leads.empty_unmatched_description', 'Your current discovered leads all overlap with the resume, or they are hidden by the active filters.')
                            : t('job_leads.empty_all_description', 'No discovered leads match the current workspace filters right now.')"
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
                        data-testid="job-lead-card"
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
                                    v-if="resumeReady && !isMatchedWorkspace"
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="resumeOverlapState(jobLead) === 'matched'
                                        ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                                        : 'border-sky-400/20 bg-sky-400/10 text-sky-200'"
                                >
                                    {{ resumeOverlapState(jobLead) === 'matched'
                                        ? t('job_leads.card_matched_lead', 'Matched lead')
                                        : t('job_leads.card_broader_lead', 'Broader IT lead') }}
                                </span>
                                <span
                                    v-if="locationClassificationLabel(jobLead.location_classification)"
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="locationClassificationClasses(jobLead.location_classification)"
                                >
                                    {{ locationClassificationLabel(jobLead.location_classification) }}
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
                                <span
                                    v-if="jobLead.preference_fit"
                                    class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="preferenceFitClasses(jobLead.preference_fit)"
                                >
                                    {{ preferenceFitLabel(jobLead.preference_fit) }}
                                </span>
                            </div>

                            <div
                                v-if="jobLead.why_this_job"
                                class="mt-4 rounded-2xl border border-white/8 bg-black/15 px-4 py-3 text-sm leading-6 text-slateglass-300"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400">
                                    {{ t('matched_jobs.why_this_job', 'Why this job?') }}
                                </p>
                                <p
                                    v-if="whyThisJobPreferenceLabel(jobLead.why_this_job)"
                                    class="mt-2 text-slateglass-200"
                                >
                                    {{ whyThisJobPreferenceLabel(jobLead.why_this_job) }}
                                </p>
                                <p
                                    v-if="jobLead.why_this_job.matched_keywords.length > 0"
                                    class="mt-1"
                                >
                                    <span class="font-semibold text-slateglass-200">
                                        {{ t('matched_jobs.matches', 'Matches') }}:
                                    </span>
                                    {{ keywordSummary(jobLead.why_this_job.matched_keywords) }}
                                </p>
                                <p
                                    v-if="jobLead.why_this_job.missing_keywords.length > 0"
                                    class="mt-1"
                                >
                                    <span class="font-semibold text-slateglass-200">
                                        {{ t('matched_jobs.missing', 'Missing') }}:
                                    </span>
                                    {{ keywordSummary(jobLead.why_this_job.missing_keywords) }}
                                </p>
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

                            <div
                                v-if="jobLead.preference_fit"
                                class="mt-5 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="reason in preferenceFitReasons(jobLead)"
                                    :key="reason.key"
                                    class="rounded-full border px-3 py-1 text-xs font-medium"
                                    :class="reason.tone === 'match'
                                        ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                                        : 'border-red-400/20 bg-red-400/10 text-red-200'"
                                >
                                    {{ reason.label }}
                                </span>
                            </div>

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
                                <a
                                    v-if="jobLead.source_post_url"
                                    :href="jobLead.source_post_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="premium-button-secondary"
                                >
                                    {{ t('buttons.view_source_post', 'View source post') }}
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
