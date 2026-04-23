<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    source_url: '',
    source_name: '',
    company_name: '',
    job_title: '',
});
const { t } = useI18n();

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
            <label for="import_source_url" class="premium-input-label">{{ t('job_lead_form.source_url', 'Job URL') }}</label>
            <TextInput
                id="import_source_url"
                v-model="form.source_url"
                type="url"
                class="mt-2 block w-full"
                :placeholder="t('job_lead_form.source_url_placeholder', 'https://company.com/jobs/senior-product-engineer')"
                required
            />
            <InputError class="mt-2" :message="form.errors.source_url" />
        </div>

        <div>
            <label for="import_company_name" class="premium-input-label">{{ t('job_lead_form.company_name', 'Company name') }}</label>
            <TextInput
                id="import_company_name"
                v-model="form.company_name"
                type="text"
                class="mt-2 block w-full"
                :placeholder="t('job_lead_import.company_name_placeholder', 'Optional')"
            />
            <InputError class="mt-2" :message="form.errors.company_name" />
        </div>

        <div>
            <label for="import_job_title" class="premium-input-label">{{ t('job_lead_form.job_title', 'Job title') }}</label>
            <TextInput
                id="import_job_title"
                v-model="form.job_title"
                type="text"
                class="mt-2 block w-full"
                :placeholder="t('job_lead_import.job_title_placeholder', 'Optional')"
            />
            <InputError class="mt-2" :message="form.errors.job_title" />
        </div>

        <div>
            <label for="import_source_name" class="premium-input-label">{{ t('job_lead_form.source_name', 'Source name') }}</label>
            <TextInput
                id="import_source_name"
                v-model="form.source_name"
                type="text"
                class="mt-2 block w-full"
                :placeholder="t('job_lead_import.source_name_placeholder', 'LinkedIn, company site, etc.')"
            />
            <InputError class="mt-2" :message="form.errors.source_name" />
        </div>

        <div class="flex items-end">
            <div class="w-full rounded-3xl border border-gold-400/15 bg-white/5 px-4 py-4 text-sm text-slateglass-300">
                {{ t('job_lead_import.import_hint', 'Import stores the URL now with a saved status. Parsing and enrichment can plug into this flow later.') }}
            </div>
        </div>

        <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="premium-button-primary"
                :disabled="form.processing"
            >
                {{ t('job_lead_import.import_button', 'Import lead') }}
            </button>
            <p class="text-sm text-slateglass-400">
                {{ t('job_lead_import.metadata_hint', 'Optional metadata helps triage before deeper extraction exists.') }}
            </p>
        </div>
    </form>
</template>
