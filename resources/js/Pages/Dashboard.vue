<script setup>
import ApplicationStatusBadge from '@/Components/ApplicationStatusBadge.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MetricCard from '@/Components/ui/MetricCard.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
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
            <AppShell
                title="Your application pipeline"
                subtitle="A clean snapshot of momentum, open loops, and the opportunities worth your attention next."
            >
                <template #actions>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="route('applications.index')"
                            class="premium-button-secondary"
                        >
                            View pipeline
                        </Link>
                        <Link
                            :href="route('applications.create')"
                            class="premium-button-primary"
                        >
                            Add application
                        </Link>
                    </div>
                </template>
            </AppShell>
        </template>

        <AppShell>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2 xl:col-span-2">
                    <MetricCard
                        label="Total applications"
                        :value="totalApplications"
                        tone="accent"
                    >
                        <p class="mt-3 text-sm leading-6 text-slateglass-300">
                            Your complete pipeline across wishlist, active, and closed outcomes.
                        </p>
                    </MetricCard>
                </div>

                <MetricCard
                    v-for="metric in metricCards"
                    :key="metric.key"
                    :label="metric.label"
                    :value="metric.value"
                />
            </div>

            <SectionCard
                title="Recent applications"
                description="Your latest tracked opportunities, with status and applied date at a glance."
                :padded="false"
            >
                <template #actions>
                    <Link
                        :href="route('applications.index')"
                        class="premium-link"
                    >
                        View all
                    </Link>
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        New application
                    </Link>
                </template>

                <EmptyState
                    v-if="applications.length === 0"
                    title="No applications yet"
                    description="Start the pipeline with your first tracked opportunity and the dashboard will begin to fill with signal."
                >
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        Create application
                    </Link>
                </EmptyState>

                <div
                    v-else
                    class="divide-y divide-white/10"
                >
                    <div
                        v-for="application in applications"
                        :key="application.id"
                        class="flex flex-col gap-5 px-6 py-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-lg font-semibold text-white">
                                    {{ application.company_name }}
                                </h3>
                                <ApplicationStatusBadge :status="application.status" />
                            </div>
                            <p class="mt-2 text-sm text-slateglass-300">
                                {{ application.job_title }}
                            </p>
                            <p class="mt-3 text-xs font-medium uppercase tracking-[0.2em] text-slateglass-400">
                                Applied at {{ application.applied_at || 'Not set' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('applications.edit', application.id)"
                                class="premium-button-secondary"
                            >
                                Open
                            </Link>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
