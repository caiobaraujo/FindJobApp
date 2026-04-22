<script setup>
import ApplicationStatusBadge from '@/Components/ApplicationStatusBadge.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    applications: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const filterForm = reactive({
    status: props.filters.status || '',
    search: props.filters.search || '',
});

function submitFilters() {
    router.get(route('applications.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.status = '';
    filterForm.search = '';
    submitFilters();
}

function destroyApplication(id) {
    if (! window.confirm('Delete this application?')) {
        return;
    }

    router.delete(route('applications.destroy', id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Applications" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Pipeline"
                    title="Applications"
                    description="Track every opportunity with cleaner filters, clearer status, and a better operational view."
                >
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        New application
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Filter pipeline"
                description="Narrow the list by stage or search across company and role."
            >
                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[220px_1fr_auto]">
                    <div>
                        <label for="status" class="premium-input-label">Status</label>
                        <select
                            id="status"
                            v-model="filterForm.status"
                            class="mt-2 block w-full"
                        >
                            <option value="">All statuses</option>
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="search" class="premium-input-label">Search</label>
                        <input
                            id="search"
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 block w-full"
                            placeholder="Company or job title"
                        >
                    </div>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="premium-button-primary"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            class="premium-button-secondary"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard
                title="Tracked opportunities"
                description="A premium view of the current search pipeline."
                :padded="false"
            >
                <template #actions>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                        {{ applications.data.length }} visible
                    </span>
                </template>

                <EmptyState
                    v-if="applications.data.length === 0"
                    title="No applications found"
                    description="Adjust the filters or add a new opportunity to start shaping the tracker."
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
                        v-for="application in applications.data"
                        :key="application.id"
                        class="flex flex-col gap-5 px-6 py-6 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-xl font-semibold text-white">
                                    {{ application.company_name }}
                                </h3>
                                <ApplicationStatusBadge :status="application.status" />
                            </div>
                            <p class="mt-2 text-sm text-slateglass-300">
                                {{ application.job_title }}
                            </p>
                            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium uppercase tracking-[0.2em] text-slateglass-400">
                                <span>Applied at {{ application.applied_at || 'Not set' }}</span>
                                <a
                                    v-if="application.source_url"
                                    :href="application.source_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="text-gold-300 transition hover:text-gold-200"
                                >
                                    Open source
                                </a>
                            </div>
                            <p
                                v-if="application.notes"
                                class="mt-4 max-w-2xl text-sm leading-6 text-slateglass-400"
                            >
                                {{ application.notes }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                :href="route('applications.edit', application.id)"
                                class="premium-button-secondary"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="premium-button-danger"
                                @click="destroyApplication(application.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="applications.links.length > 3"
                    class="flex flex-wrap gap-2 border-t border-white/10 px-6 py-5"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in applications.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        v-html="link.label"
                        class="rounded-2xl px-4 py-2 text-sm font-medium"
                        :class="link.active
                            ? 'bg-gold-400/15 text-gold-300'
                            : 'border border-white/10 bg-white/5 text-slateglass-300 hover:bg-white/10 hover:text-white'"
                    />
                </div>
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
