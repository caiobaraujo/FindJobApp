<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import JobLeadForm from '@/Components/JobLeadForm.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    jobLead: {
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

function submit() {
    form.put(route('job-leads.update', props.jobLead.id));
}
</script>

<template>
    <Head title="Edit Job Lead" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Discovery"
                    title="Edit job lead"
                    description="Keep the lead accurate, update the full job description, and use ATS insights to decide how your resume should adapt."
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        Back to job leads
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Lead details"
                description="Update the discovery record, keep personal notes separate, and make sure the ATS analysis input stays current."
            >
                <JobLeadForm
                    :form="form"
                    :lead-statuses="leadStatuses"
                    :work-modes="workModes"
                    submit-label="Save changes"
                    @submit="submit"
                />
            </SectionCard>

            <SectionCard
                title="ATS review"
                description="Use the extracted keywords and hints to decide what language your resume should mirror for this role."
            >
                <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                                    Extracted keywords
                                </p>
                                <h3 class="mt-2 text-lg font-semibold text-white">
                                    Resume keyword targets
                                </h3>
                            </div>
                            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
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

                    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                            ATS hints
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-white">
                            Resume guidance
                        </h3>

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
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
