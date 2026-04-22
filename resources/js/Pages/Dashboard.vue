<script setup>
import ApplicationStatusBadge from '@/Components/ApplicationStatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    applications: {
        type: Array,
        required: true,
    },
    statusCounts: {
        type: Object,
        required: true,
    },
    totalApplications: {
        type: Number,
        required: true,
    },
});

const metricCards = [
    {
        key: 'wishlist',
        label: 'Wishlist',
        value: props.statusCounts.wishlist ?? 0,
    },
    {
        key: 'applied',
        label: 'Applied',
        value: props.statusCounts.applied ?? 0,
    },
    {
        key: 'interview',
        label: 'Interview',
        value: props.statusCounts.interview ?? 0,
    },
    {
        key: 'offer',
        label: 'Offer',
        value: props.statusCounts.offer ?? 0,
    },
    {
        key: 'rejected',
        label: 'Rejected',
        value: props.statusCounts.rejected ?? 0,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Job application dashboard
            </h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm md:col-span-3 xl:col-span-2">
                        <p class="text-sm font-medium text-gray-500">Total applications</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ totalApplications }}</p>
                        <p class="mt-2 text-sm text-gray-500">Your pipeline across all statuses.</p>
                    </div>

                    <div
                        v-for="metric in metricCards"
                        :key="metric.key"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-sm font-medium text-gray-500">{{ metric.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ metric.value }}</p>
                    </div>
                </div>

                <div class="mt-8 rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Recent applications</h3>
                            <p class="text-sm text-gray-500">Your latest tracked opportunities.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('applications.index')"
                                class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                View all
                            </Link>
                            <Link
                                :href="route('applications.create')"
                                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                            >
                                New application
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="applications.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <h4 class="text-lg font-semibold text-gray-900">No applications yet</h4>
                        <p class="mt-2 text-sm text-gray-500">Create your first tracked opportunity to start building your pipeline.</p>
                        <Link
                            :href="route('applications.create')"
                            class="mt-4 inline-flex rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                        >
                            Create application
                        </Link>
                    </div>

                    <div
                        v-else
                        class="divide-y divide-gray-200"
                    >
                        <div
                            v-for="application in applications"
                            :key="application.id"
                            class="flex flex-col gap-4 px-6 py-5 md:flex-row md:items-center md:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-3">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ application.company_name }}
                                    </h4>
                                    <ApplicationStatusBadge :status="application.status" />
                                </div>
                                <p class="mt-1 text-sm text-gray-600">{{ application.job_title }}</p>
                                <p class="mt-2 text-xs text-gray-500">
                                    Applied at: {{ application.applied_at || 'Not set' }}
                                </p>
                            </div>

                            <Link
                                :href="route('applications.edit', application.id)"
                                class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Edit
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
