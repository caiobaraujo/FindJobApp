<script setup>
import ApplicationStatusBadge from '@/Components/ApplicationStatusBadge.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import { useI18n } from '@/composables/useI18n';
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
    pipelineColumns: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();

const filterForm = reactive({
    status: props.filters.status || '',
    search: props.filters.search || '',
    view: props.filters.view || 'list',
});

const dragState = reactive({
    applicationId: null,
    sourceStatus: '',
    targetStatus: '',
    processing: false,
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

function switchView(view) {
    filterForm.view = view;
    submitFilters();
}

function startDrag(application) {
    if (dragState.processing) {
        return;
    }

    dragState.applicationId = application.id;
    dragState.sourceStatus = application.status;
    dragState.targetStatus = '';
}

function endDrag() {
    dragState.applicationId = null;
    dragState.sourceStatus = '';
    dragState.targetStatus = '';
}

function enterColumn(status) {
    if (dragState.processing || dragState.applicationId === null) {
        return;
    }

    dragState.targetStatus = status;
}

function leaveColumn(status) {
    if (dragState.targetStatus !== status) {
        return;
    }

    dragState.targetStatus = '';
}

function dropOnColumn(status) {
    if (dragState.processing || dragState.applicationId === null) {
        return;
    }

    if (dragState.sourceStatus === status) {
        endDrag();
        return;
    }

    dragState.processing = true;

    router.patch(
        route('applications.status.update', dragState.applicationId),
        { status },
        {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                dragState.processing = false;
                endDrag();
            },
        },
    );
}

function destroyApplication(id) {
    if (! window.confirm(t('applications.delete_confirmation', 'Delete this application?'))) {
        return;
    }

    router.delete(route('applications.destroy', id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('applications.title', 'Applications')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('applications.eyebrow', 'Pipeline')"
                    :title="t('applications.title', 'Applications')"
                    :description="t('applications.description', 'Track opportunities after discovery. Job Leads stays the main workspace for sourcing, enrichment, and prioritization.')"
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.back_to_job_leads', 'Back to job leads') }}
                    </Link>
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.new_application', 'New application') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                :title="t('applications.filter_title', 'Filter pipeline')"
                :description="t('applications.filter_description', 'Narrow the list by stage or search across company and role.')"
            >
                <form @submit.prevent="submitFilters" class="grid gap-4 xl:grid-cols-[220px_1fr_auto]">
                    <div>
                        <label for="status" class="premium-input-label">{{ t('applications.status', 'Status') }}</label>
                        <select
                            id="status"
                            v-model="filterForm.status"
                            class="mt-2 block w-full"
                        >
                            <option value="">{{ t('applications.all_statuses', 'All statuses') }}</option>
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ t(`applications.statuses.${status}`, status) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="search" class="premium-input-label">{{ t('applications.search', 'Search') }}</label>
                        <input
                            id="search"
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 block w-full"
                            :placeholder="t('applications.search_placeholder', 'Company or job title')"
                        >
                    </div>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="premium-button-primary"
                        >
                            {{ t('matched_jobs.apply', 'Apply') }}
                        </button>
                        <button
                            type="button"
                            class="premium-button-secondary"
                            @click="resetFilters"
                        >
                            {{ t('matched_jobs.reset', 'Reset') }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard
                :title="t('applications.tracked_title', 'Tracked opportunities')"
                :description="t('applications.tracked_description', 'A premium view of the current search pipeline.')"
                :padded="false"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-full border border-white/10 bg-white/5 p-1">
                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition"
                                :class="filterForm.view === 'list'
                                    ? 'bg-gold-400/15 text-gold-300'
                                    : 'text-slateglass-400 hover:text-white'"
                                @click="switchView('list')"
                            >
                                {{ t('applications.list_view', 'List view') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition"
                                :class="filterForm.view === 'pipeline'
                                    ? 'bg-gold-400/15 text-gold-300'
                                    : 'text-slateglass-400 hover:text-white'"
                                @click="switchView('pipeline')"
                            >
                                {{ t('applications.pipeline_view', 'Pipeline view') }}
                            </button>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slateglass-400">
                            {{ applications.data.length }} {{ t('applications.visible', 'visible') }}
                        </span>
                    </div>
                </template>

                <EmptyState
                    v-if="filterForm.view === 'list' && applications.data.length === 0"
                    :title="t('applications.empty_title', 'No applications found')"
                    :description="t('applications.empty_description', 'Adjust the filters or add a new opportunity to start shaping the tracker.')"
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('applications.open_job_leads', 'Open job leads') }}
                    </Link>
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.create_application', 'Create application') }}
                    </Link>
                </EmptyState>

                <div
                    v-else-if="filterForm.view === 'list'"
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
                                <span>{{ t('applications.applied_at', 'Applied at') }} {{ application.applied_at || t('applications.not_set', 'Not set') }}</span>
                                <a
                                    v-if="application.source_url"
                                    :href="application.source_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="text-gold-300 transition hover:text-gold-200"
                                >
                                    {{ t('buttons.open_source', 'Open source') }}
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
                                {{ t('buttons.edit', 'Edit') }}
                            </Link>
                            <button
                                type="button"
                                class="premium-button-danger"
                                @click="destroyApplication(application.id)"
                            >
                                {{ t('buttons.delete', 'Delete') }}
                            </button>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else-if="pipelineColumns.every((column) => column.count === 0)"
                    :title="t('applications.empty_pipeline_title', 'No pipeline cards yet')"
                    :description="t('applications.empty_pipeline_description', 'Add your first opportunity or relax the filters to populate the stage-based view.')"
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('applications.open_job_leads', 'Open job leads') }}
                    </Link>
                    <Link
                        :href="route('applications.create')"
                        class="premium-button-primary"
                    >
                        {{ t('buttons.create_application', 'Create application') }}
                    </Link>
                </EmptyState>

                <div
                    v-else
                    class="grid gap-5 p-6 xl:grid-cols-5"
                >
                    <div
                        v-for="column in pipelineColumns"
                        :key="column.key"
                        class="rounded-[1.75rem] border border-white/10 bg-white/[0.03] p-4 shadow-panel transition"
                        :class="dragState.targetStatus === column.key
                            ? 'border-gold-400/40 bg-gold-400/[0.06]'
                            : ''"
                        @dragover.prevent="enterColumn(column.key)"
                        @dragenter.prevent="enterColumn(column.key)"
                        @dragleave="leaveColumn(column.key)"
                        @drop.prevent="dropOnColumn(column.key)"
                    >
                        <div class="mb-4 flex items-center justify-between gap-3 border-b border-white/10 pb-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-white">
                                {{ t(`applications.statuses.${column.key}`, column.title) }}
                            </h3>
                            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-semibold text-slateglass-300">
                                {{ column.count }}
                            </span>
                        </div>

                        <div
                            v-if="column.applications.length === 0"
                            class="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-4 py-6 text-center text-sm text-slateglass-400"
                        >
                            {{ t('applications.no_applications', 'No applications') }}
                        </div>

                        <div
                            v-else
                            class="space-y-3"
                        >
                            <article
                                v-for="application in column.applications"
                                :key="application.id"
                                class="cursor-grab rounded-2xl border border-white/10 bg-obsidian-850/80 p-4 transition hover:border-gold-400/20 hover:bg-white/[0.05] active:cursor-grabbing"
                                :class="dragState.applicationId === application.id
                                    ? 'border-gold-400/40 opacity-60'
                                    : ''"
                                draggable="true"
                                @dragstart="startDrag(application)"
                                @dragend="endDrag"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-semibold text-white">
                                        {{ application.company_name }}
                                    </h4>
                                    <ApplicationStatusBadge :status="application.status" />
                                </div>
                                <p class="mt-2 text-sm text-slateglass-300">
                                    {{ application.job_title }}
                                </p>
                                <p
                                    v-if="application.applied_at"
                                    class="mt-3 text-xs font-medium uppercase tracking-[0.18em] text-slateglass-400"
                                >
                                    {{ t('applications.applied_at', 'Applied at') }} {{ application.applied_at }}
                                </p>
                                <div class="mt-4">
                                    <Link
                                        :href="route('applications.edit', application.id)"
                                        class="premium-link"
                                    >
                                        {{ t('buttons.edit', 'Edit') }}
                                    </Link>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filterForm.view === 'list' && applications.links.length > 3"
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
