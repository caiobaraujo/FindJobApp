<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    prefill: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    job_lead_id: props.prefill.job_lead_id ?? null,
    company_name: props.prefill.company_name ?? '',
    job_title: props.prefill.job_title ?? '',
    source_url: props.prefill.source_url ?? '',
    status: props.prefill.status ?? props.statuses[0] ?? 'wishlist',
    applied_at: props.prefill.applied_at ?? '',
    notes: props.prefill.notes ?? '',
});

function submit() {
    form.post(route('applications.store'));
}
</script>

<template>
    <Head title="Create Application" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Pipeline"
                    title="Create application"
                    :description="prefill.job_lead_id
                        ? 'Review the prefilled details from this saved job lead before creating the application.'
                        : 'Add a new opportunity with consistent structure from the first touchpoint.'"
                >
                    <Link
                        v-if="prefill.job_lead_edit_url"
                        :href="prefill.job_lead_edit_url"
                        class="premium-button-secondary"
                    >
                        Back to job lead
                    </Link>
                    <Link
                        :href="route('applications.index')"
                        class="premium-button-secondary"
                    >
                        Back to applications
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Application details"
                :description="prefill.job_lead_id
                    ? 'The job lead filled the basics for you. Confirm the details and adjust anything before saving.'
                    : 'Capture the company, role, stage, and source in one pass.'"
            >
                <ApplicationForm
                    :form="form"
                    :statuses="statuses"
                    submit-label="Create application"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
