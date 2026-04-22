<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
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
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit application</h2>
                    <p class="mt-1 text-sm text-gray-500">Keep the application details and status current.</p>
                </div>
                <Link
                    :href="route('applications.index')"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    Back to applications
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <ApplicationForm
                        :form="form"
                        :statuses="statuses"
                        submit-label="Save changes"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
