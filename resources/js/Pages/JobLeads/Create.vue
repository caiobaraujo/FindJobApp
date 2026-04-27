<script setup>
import AppShell from '@/Components/ui/AppShell.vue';
import BulkJobLeadImportForm from '@/Components/BulkJobLeadImportForm.vue';
import JobLeadForm from '@/Components/JobLeadForm.vue';
import { useI18n } from '@/composables/useI18n';
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

const { t } = useI18n();

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
    <Head :title="t('job_lead_create.title', 'Add job')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('job_lead_create.eyebrow', 'Discovery')"
                    :title="t('job_lead_create.title', 'Add job')"
                    :description="t('job_lead_create.description', 'URL-only intake saves the lead. Paste job text now if you want keyword analysis immediately.')"
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.back_to_matched_jobs', 'Back to matched jobs') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <div class="grid gap-6 xl:grid-cols-2">
                <SectionCard
                    :title="t('job_lead_create.card_title', 'Start with the job URL')"
                    :description="t('job_lead_create.card_description', 'URL-first intake keeps discovery fast. URL-only intake saves the lead. Paste job text if you want matching signals right away.')"
                >
                    <JobLeadForm
                        :form="form"
                        :lead-statuses="leadStatuses"
                        :work-modes="workModes"
                        mode="create"
                        :submit-label="t('buttons.add_job', 'Add job')"
                        @submit="submit"
                    />
                </SectionCard>

                <SectionCard
                    :title="t('job_lead_bulk_import.title', 'Paste multiple job URLs')"
                    :description="t('job_lead_bulk_import.description', 'Bulk URL intake is a faster bridge to future discovery. You provide the URLs and the app saves each valid one as an honest, URL-only lead.')"
                >
                    <BulkJobLeadImportForm />
                </SectionCard>
            </div>
        </AppShell>
    </AuthenticatedLayout>
</template>
