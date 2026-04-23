<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import { useI18n } from '@/composables/useI18n';
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

const { t } = useI18n();

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
    <Head :title="t('applications.create_title', 'Create application')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('applications.eyebrow', 'Pipeline')"
                    :title="t('applications.create_title', 'Create application')"
                    :description="prefill.job_lead_id
                        ? t('applications.create_from_job_lead_description', 'Review the prefilled details from this saved job lead before creating the application.')
                        : t('applications.create_description', 'Add a new opportunity with consistent structure from the first touchpoint.')"
                >
                    <Link
                        v-if="prefill.job_lead_edit_url"
                        :href="prefill.job_lead_edit_url"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.back_to_job_leads', 'Back to job leads') }}
                    </Link>
                    <Link
                        :href="route('applications.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.back_to_applications', 'Back to applications') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                :title="t('applications.application_details', 'Application details')"
                :description="prefill.job_lead_id
                    ? t('applications.create_from_job_lead_card_description', 'The job lead filled the basics for you. Confirm the details and adjust anything before saving.')
                    : t('applications.create_card_description', 'Capture the company, role, stage, and source in one pass.')"
            >
                <ApplicationForm
                    :form="form"
                    :statuses="statuses"
                    :submit-label="t('buttons.create_application', 'Create application')"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
