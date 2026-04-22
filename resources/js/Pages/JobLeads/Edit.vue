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
                    description="Keep the lead accurate and scored so prioritization stays aligned with the opportunities that matter most."
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
                description="Update the discovery record and relevance score without losing the source signals that make it useful later."
            >
                <JobLeadForm
                    :form="form"
                    :lead-statuses="leadStatuses"
                    :work-modes="workModes"
                    submit-label="Save changes"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
