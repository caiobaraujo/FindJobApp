<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import JobLeadForm from '@/Components/JobLeadForm.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
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
    company_name: '',
    job_title: '',
    source_name: '',
    source_url: '',
    location: '',
    work_mode: '',
    salary_range: '',
    description_excerpt: '',
    relevance_score: '',
    lead_status: props.leadStatuses[0] ?? 'saved',
    discovered_at: '',
});

function submit() {
    form.post(route('job-leads.store'));
}
</script>

<template>
    <Head title="Create Job Lead" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Discovery"
                    title="Create job lead"
                    description="Capture a promising opportunity with enough metadata to support future qualification, prioritization, and application optimization."
                >
                    <a
                        :href="`${route('job-leads.index')}#import-job-lead`"
                        class="premium-button-secondary"
                    >
                        Import from URL
                    </a>
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
                description="Store the source context and an initial score so the best opportunities rise first as your discovery workspace grows."
            >
                <JobLeadForm
                    :form="form"
                    :lead-statuses="leadStatuses"
                    :work-modes="workModes"
                    submit-label="Create job lead"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
