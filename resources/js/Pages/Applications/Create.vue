<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    statuses: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    company_name: '',
    job_title: '',
    source_url: '',
    status: props.statuses[0] ?? 'wishlist',
    applied_at: '',
    notes: '',
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
                    description="Add a new opportunity with consistent structure from the first touchpoint."
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
                description="Capture the company, role, stage, and source in one pass."
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
