<script setup>
import InputError from '@/Components/InputError.vue';
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    source_urls: '',
});

const { t } = useI18n();

function submit() {
    form.post(route('job-leads.bulk-import'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit">
        <div>
            <label for="bulk_source_urls" class="premium-input-label">{{ t('job_lead_bulk_import.source_urls', 'Job URLs') }}</label>
            <textarea
                id="bulk_source_urls"
                v-model="form.source_urls"
                rows="8"
                class="mt-2 block w-full rounded-3xl border-white/10 bg-black/20 text-white shadow-none focus:border-gold-300/40 focus:ring-gold-300/30"
                :placeholder="t('job_lead_bulk_import.source_urls_placeholder', 'https://company.com/jobs/role-one\nhttps://company.com/jobs/role-two')"
                required
            />
            <p class="mt-2 text-sm text-slateglass-400">
                {{ t('job_lead_bulk_import.separator_hint', 'Paste up to 50 URLs. Separate them with new lines, spaces, or commas.') }}
            </p>
            <InputError class="mt-2" :message="form.errors.source_urls" />
        </div>

        <div class="rounded-3xl border border-gold-400/15 bg-white/5 px-4 py-4 text-sm text-slateglass-300">
            {{ t('job_lead_bulk_import.honesty_hint', 'Bulk import only saves the URLs as honest leads. It does not fetch job pages or invent missing job details.') }}
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="premium-button-primary"
                :disabled="form.processing"
            >
                {{ t('job_lead_bulk_import.import_button', 'Import URLs') }}
            </button>
            <p class="text-sm text-slateglass-400">
                {{ t('job_lead_bulk_import.limited_analysis_hint', 'Imported URL-only leads stay clickable and will show limited-analysis messaging until you add job description text.') }}
            </p>
        </div>
    </form>
</template>
