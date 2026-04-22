<script setup>
import ApplicationForm from '@/Components/ApplicationForm.vue';
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
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Create application</h2>
                    <p class="mt-1 text-sm text-gray-500">Add a new job opportunity to your tracking workflow.</p>
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
                        submit-label="Create application"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
