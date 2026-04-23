<script setup>
import JobLeadMatchCard from '@/Components/JobLeadMatchCard.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import JobLeadForm from '@/Components/JobLeadForm.vue';
import { useI18n } from '@/composables/useI18n';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    jobLead: {
        type: Object,
        required: true,
    },
    matchAnalysis: {
        type: Object,
        required: true,
    },
    leadStatuses: {
        type: Array,
        required: true,
    },
    workModes: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    company_name: props.jobLead.company_name,
    job_title: props.jobLead.job_title,
    source_name: props.jobLead.source_name ?? '',
    source_url: props.jobLead.source_url,
    location: props.jobLead.location ?? '',
    work_mode: props.jobLead.work_mode ?? '',
    salary_range: props.jobLead.salary_range ?? '',
    description_excerpt: props.jobLead.description_excerpt ?? '',
    description_text: props.jobLead.description_text ?? '',
    relevance_score: props.jobLead.relevance_score ?? '',
    lead_status: props.jobLead.lead_status,
    discovered_at: props.jobLead.discovered_at ?? '',
});
const deleteForm = useForm({});
const { t } = useI18n();

function submit() {
    form.put(route('job-leads.update', props.jobLead.id));
}

function deleteJobLead() {
    if (! window.confirm(t('job_lead_edit.delete_confirmation', 'Delete this saved job lead? This cannot be undone.'))) {
        return;
    }

    deleteForm.delete(route('job-leads.destroy', props.jobLead.id));
}

</script>

<template>
    <Head :title="t('job_lead_edit.title', 'Edit job lead')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('job_lead_edit.eyebrow', 'Discovery')"
                    :title="t('job_lead_edit.title', 'Edit job lead')"
                    :description="t('job_lead_edit.description', 'Keep the lead accurate, update the full job description, and use ATS insights to decide how your resume should adapt.')"
                >
                    <Link
                        :href="route('applications.create', { job_lead: jobLead.id })"
                        class="premium-button-primary"
                    >
                        {{ t('job_lead_edit.convert_to_application', 'Convert to application') }}
                    </Link>
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('job_lead_edit.back_to_jobs', 'Back to matched jobs') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                :title="t('job_lead_edit.details_title', 'Lead details')"
                :description="t('job_lead_edit.details_description', 'Update the discovery record, keep personal notes separate, and make sure the ATS analysis input stays current.')"
            >
                <JobLeadForm
                    :form="form"
                    :lead-statuses="leadStatuses"
                    :work-modes="workModes"
                    :submit-label="t('job_lead_edit.save_changes', 'Save changes')"
                    @submit="submit"
                />
            </SectionCard>

            <SectionCard
                :title="t('job_lead_edit.ats_review_title', 'ATS review')"
                :description="t('job_lead_edit.ats_review_description', 'Use the extracted keywords and hints to decide what language your resume should mirror for this role.')"
            >
                <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                                    {{ t('job_lead_edit.extracted_keywords_label', 'Extracted keywords') }}
                                </p>
                                <h3 class="mt-2 text-lg font-semibold text-white">
                                    {{ t('job_lead_edit.resume_keyword_targets', 'Resume keyword targets') }}
                                </h3>
                            </div>
                            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                                {{ jobLead.extracted_keywords.length }} {{ t('job_lead_edit.found', 'found') }}
                            </span>
                        </div>

                        <p
                            v-if="!jobLead.description_text"
                            class="mt-4 text-sm text-slateglass-400"
                        >
                            {{ t('job_lead_edit.empty_description', 'Add a job description to unlock ATS insights') }}
                        </p>

                        <p
                            v-else-if="jobLead.extracted_keywords.length === 0"
                            class="mt-4 text-sm text-slateglass-400"
                        >
                            {{ t('job_lead_edit.no_keywords', 'No keywords extracted yet') }}
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

                    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                            {{ t('job_lead_edit.ats_hints_label', 'ATS hints') }}
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-white">
                            {{ t('job_lead_edit.resume_guidance', 'Resume guidance') }}
                        </h3>

                        <p
                            v-if="!jobLead.description_text"
                            class="mt-4 text-sm text-slateglass-400"
                        >
                            {{ t('job_lead_edit.empty_description', 'Add a job description to unlock ATS insights') }}
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
            </SectionCard>

            <SectionCard
                :title="t('job_lead_edit.resume_match_title', 'Resume match')"
                :description="t('job_lead_edit.resume_match_description', 'Compare this job lead against your resume profile to see what your current base resume already covers and what is still missing.')"
            >
                <JobLeadMatchCard :analysis="matchAnalysis" />
            </SectionCard>

            <SectionCard
                :title="t('job_lead_edit.delete_title', 'Delete saved lead')"
                :description="t('job_lead_edit.delete_description', 'Remove this job lead from your saved jobs when it is no longer useful for matching or follow-up.')"
            >
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-red-400/15 bg-red-400/[0.06] p-5">
                    <p class="max-w-2xl text-sm leading-7 text-slateglass-200">
                        {{ t('job_lead_edit.delete_warning', 'Deleting this lead removes it from matched jobs and cannot be undone.') }}
                    </p>
                    <button
                        type="button"
                        class="rounded-full border border-red-300/30 bg-red-400/10 px-5 py-3 text-sm font-semibold text-red-100 transition hover:border-red-200/60 hover:bg-red-400/20 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="deleteForm.processing"
                        @click="deleteJobLead"
                    >
                        {{ t('job_lead_edit.delete_button', 'Delete job lead') }}
                    </button>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
