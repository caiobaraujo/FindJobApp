<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    source_url: '',
    source_name: '',
    company_name: '',
    job_title: '',
});

function submit() {
    form.post(route('job-leads.import'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form class="grid gap-5 lg:grid-cols-2" @submit.prevent="submit">
        <div class="lg:col-span-2">
            <label for="import_source_url" class="premium-input-label">Job URL</label>
            <TextInput
                id="import_source_url"
                v-model="form.source_url"
                type="url"
                class="mt-2 block w-full"
                placeholder="https://company.com/jobs/senior-product-engineer"
                required
            />
            <InputError class="mt-2" :message="form.errors.source_url" />
        </div>

        <div>
            <label for="import_company_name" class="premium-input-label">Company name</label>
            <TextInput
                id="import_company_name"
                v-model="form.company_name"
                type="text"
                class="mt-2 block w-full"
                placeholder="Optional"
            />
            <InputError class="mt-2" :message="form.errors.company_name" />
        </div>

        <div>
            <label for="import_job_title" class="premium-input-label">Job title</label>
            <TextInput
                id="import_job_title"
                v-model="form.job_title"
                type="text"
                class="mt-2 block w-full"
                placeholder="Optional"
            />
            <InputError class="mt-2" :message="form.errors.job_title" />
        </div>

        <div>
            <label for="import_source_name" class="premium-input-label">Source name</label>
            <TextInput
                id="import_source_name"
                v-model="form.source_name"
                type="text"
                class="mt-2 block w-full"
                placeholder="LinkedIn, company site, etc."
            />
            <InputError class="mt-2" :message="form.errors.source_name" />
        </div>

        <div class="flex items-end">
            <div class="w-full rounded-3xl border border-gold-400/15 bg-white/5 px-4 py-4 text-sm text-slateglass-300">
                Import stores the URL now with a saved status. Parsing and enrichment can plug into this flow later.
            </div>
        </div>

        <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="premium-button-primary"
                :disabled="form.processing"
            >
                Import lead
            </button>
            <p class="text-sm text-slateglass-400">
                Optional metadata helps triage before deeper extraction exists.
            </p>
        </div>
    </form>
</template>
