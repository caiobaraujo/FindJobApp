<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
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
    <Head title="Edit Application" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Pipeline"
                    title="Edit application"
                    description="Update the current stage and keep the opportunity record precise."
                >
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
                description="Adjust the record without leaving the shared design system."
            >
                <ApplicationForm
                    :form="form"
                    :statuses="statuses"
                    submit-label="Save changes"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
