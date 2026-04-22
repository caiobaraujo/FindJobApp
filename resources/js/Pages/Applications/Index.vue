<script setup>
import ApplicationStatusBadge from '@/Components/ApplicationStatusBadge.vue';
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
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Applications</h2>
                    <p class="mt-1 text-sm text-gray-500">Track your opportunities and keep the pipeline current.</p>
                </div>
                <Link
                    :href="route('applications.create')"
                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                >
                    New application
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <form @submit.prevent="submitFilters" class="grid gap-4 md:grid-cols-[200px_1fr_auto]">
                        <div>
                            <label for="status" class="text-sm font-medium text-gray-700">Status</label>
                            <select
                                id="status"
                                v-model="filterForm.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            <label for="search" class="text-sm font-medium text-gray-700">Search</label>
                            <input
                                id="search"
                                v-model="filterForm.search"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Company or job title"
                            >
                        </div>

                        <div class="flex items-end gap-3">
                            <button
                                type="submit"
                                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div
                        v-if="applications.data.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <h3 class="text-lg font-semibold text-gray-900">No applications found</h3>
                        <p class="mt-2 text-sm text-gray-500">Try changing the filters or create a new application.</p>
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
                            v-for="application in applications.data"
                            :key="application.id"
                            class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ application.company_name }}
                                    </h3>
                                    <ApplicationStatusBadge :status="application.status" />
                                </div>
                                <p class="mt-1 text-sm text-gray-600">{{ application.job_title }}</p>
                                <div class="mt-3 space-y-1 text-sm text-gray-500">
                                    <p>Applied at: {{ application.applied_at || 'Not set' }}</p>
                                    <p v-if="application.source_url">
                                        <a
                                            :href="application.source_url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="font-medium text-sky-700 hover:text-sky-800"
                                        >
                                            Open source link
                                        </a>
                                    </p>
                                    <p v-if="application.notes" class="max-w-2xl">
                                        {{ application.notes }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Link
                                    :href="route('applications.edit', application.id)"
                                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:text-red-700"
                                    @click="destroyApplication(application.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="applications.links.length > 3"
                        class="flex flex-wrap gap-2 border-t border-gray-200 px-6 py-4"
                    >
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in applications.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            v-html="link.label"
                            class="rounded-md px-3 py-2 text-sm"
                            :class="link.active
                                ? 'bg-gray-900 text-white'
                                : 'border border-gray-300 text-gray-600 hover:bg-gray-50'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
