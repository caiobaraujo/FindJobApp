<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import { useI18n } from '@/composables/useI18n';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    application: {
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
    company_name: props.application.company_name,
    job_title: props.application.job_title,
    source_url: props.application.source_url ?? '',
    status: props.application.status,
    applied_at: props.application.applied_at ?? '',
    notes: props.application.notes ?? '',
});

function submit() {
    form.put(route('applications.update', props.application.id));
}
</script>

<template>
    <Head :title="t('applications.edit_title', 'Edit application')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('applications.eyebrow', 'Pipeline')"
                    :title="t('applications.edit_title', 'Edit application')"
                    :description="t('applications.edit_description', 'Update the current stage and keep the opportunity record precise.')"
                >
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
                :description="t('applications.edit_card_description', 'Adjust the record without leaving the shared design system.')"
            >
                <ApplicationForm
                    :form="form"
                    :statuses="statuses"
                    :submit-label="t('buttons.save_changes', 'Save changes')"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
