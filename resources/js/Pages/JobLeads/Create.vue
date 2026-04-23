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
    description_text: '',
    relevance_score: '',
    lead_status: props.leadStatuses[0] ?? 'saved',
    discovered_at: '',
});

function submit() {
    form.transform((data) => Object.fromEntries(
        Object.entries(data).filter(([, value]) => value !== ''),
    )).post(route('job-leads.store'), {
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
    <Head title="Create Job Lead" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Discovery"
                    title="Add job"
                    description="URL-only intake saves the lead. Paste job text now if you want keyword analysis immediately."
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        Back to matched jobs
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Start with the job URL"
                description="URL-first intake keeps discovery fast. URL-only intake saves the lead. Paste job text if you want matching signals right away."
            >
                <JobLeadForm
                    :form="form"
                    :lead-statuses="leadStatuses"
                    :work-modes="workModes"
                    mode="create"
                    submit-label="Add job"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
