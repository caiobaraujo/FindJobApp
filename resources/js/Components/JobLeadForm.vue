<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/composables/useI18n';
import { nextTick, onMounted, ref } from 'vue';

defineEmits(['submit']);

defineProps({
    form: {
        type: Object,
        required: true,
    },
    leadStatuses: {
        type: Array,
        required: true,
    },
    submitLabel: {
        type: String,
        required: true,
    },
    mode: {
        type: String,
        default: 'edit',
    },
    focusDescription: {
        type: Boolean,
        default: false,
    },
    workModes: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();
const descriptionTextarea = ref(null);

onMounted(() => {
    if (! props.focusDescription) {
        return;
    }

    nextTick(() => {
        descriptionTextarea.value?.focus();
        descriptionTextarea.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    });
});

function workModeLabel(workMode) {
    return t(`job_lead_form.work_modes.${workMode}`, workMode);
}

function leadStatusLabel(leadStatus) {
    return t(`job_lead_form.statuses.${leadStatus}`, leadStatus);
}
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="space-y-6">
        <div class="rounded-3xl border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-6">
            <div>
                <label for="source_url" class="premium-input-label">{{ t('job_lead_form.source_url', 'Job URL') }}</label>
                <p class="mt-2 text-sm leading-7 text-slateglass-300">
                    {{ t('job_lead_form.source_url_description', 'You can start with just the job URL. URL-only intake saves the lead. Paste the job description below to extract keywords now.') }}
                </p>
                <TextInput
                    id="source_url"
                    v-model="form.source_url"
                    type="url"
                    class="mt-2 block w-full"
                    autofocus
                    required
                    :placeholder="t('job_lead_form.source_url_placeholder', 'https://company.com/jobs/senior-product-engineer')"
                />
                <InputError class="mt-2" :message="form.errors.source_url" />
            </div>

            <div
                v-if="mode === 'create'"
                class="mt-6 rounded-3xl border border-white/10 bg-black/20 p-5"
            >
                <label for="description_text" class="premium-input-label">{{ t('job_lead_form.description_text_create', 'Paste job description (optional)') }}</label>
                <p class="mt-2 text-sm leading-7 text-slateglass-300">
                    {{ t('job_lead_form.description_text_create_description', 'Paste the job text here to extract keywords and improve matching immediately.') }}
                </p>
                <textarea
                    id="description_text"
                    v-model="form.description_text"
                    rows="8"
                    class="mt-4 block w-full"
                    :placeholder="t('job_lead_form.description_text_create_placeholder', 'Paste the job text here if you want ATS keyword analysis right away.')"
                />
                <InputError class="mt-2" :message="form.errors.description_text" />
                <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slateglass-400">
                    {{ t('job_lead_form.url_only_warning', 'URL-only intake does not parse job pages automatically yet.') }}
                </p>
            </div>
        </div>

        <details
            :open="mode !== 'create'"
            class="rounded-3xl border border-white/10 bg-white/[0.03] p-6"
        >
            <summary class="cursor-pointer list-none">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-400">
                            {{ t('job_lead_form.optional_details', 'Optional details') }}
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-white">
                            {{ t('job_lead_form.optional_details_title', 'Add context only if it helps') }}
                        </h3>
                    </div>
                    <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400">
                        {{ t('job_lead_form.secondary', 'Secondary') }}
                    </span>
                </div>
            </summary>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div>
                    <label for="company_name" class="premium-input-label">{{ t('job_lead_form.company_name', 'Company name') }}</label>
                    <TextInput
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.company_name_placeholder', 'Optional. Derived from the URL if empty.')"
                    />
                    <InputError class="mt-2" :message="form.errors.company_name" />
                </div>

                <div>
                    <label for="job_title" class="premium-input-label">{{ t('job_lead_form.job_title', 'Job title') }}</label>
                    <TextInput
                        id="job_title"
                        v-model="form.job_title"
                        type="text"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.job_title_placeholder', 'Optional. Imported job is used if empty.')"
                    />
                    <InputError class="mt-2" :message="form.errors.job_title" />
                </div>

                <div>
                    <label for="source_name" class="premium-input-label">{{ t('job_lead_form.source_name', 'Source name') }}</label>
                    <TextInput
                        id="source_name"
                        v-model="form.source_name"
                        type="text"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.source_name_placeholder', 'LinkedIn, Indeed, company site')"
                    />
                    <InputError class="mt-2" :message="form.errors.source_name" />
                </div>

                <div>
                    <label for="location" class="premium-input-label">{{ t('job_lead_form.location', 'Location') }}</label>
                    <TextInput
                        id="location"
                        v-model="form.location"
                        type="text"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.location_placeholder', 'New York, NY')"
                    />
                    <InputError class="mt-2" :message="form.errors.location" />
                </div>

                <div>
                    <label for="work_mode" class="premium-input-label">{{ t('job_lead_form.work_mode', 'Work mode') }}</label>
                    <select
                        id="work_mode"
                        v-model="form.work_mode"
                        class="mt-2 block w-full"
                    >
                        <option value="">{{ t('job_lead_form.work_mode_not_set', 'Not set') }}</option>
                        <option
                            v-for="workMode in workModes"
                            :key="workMode"
                            :value="workMode"
                        >
                            {{ workModeLabel(workMode) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.work_mode" />
                </div>

                <div>
                    <label for="salary_range" class="premium-input-label">{{ t('job_lead_form.salary_range', 'Salary range') }}</label>
                    <TextInput
                        id="salary_range"
                        v-model="form.salary_range"
                        type="text"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.salary_range_placeholder', '$120k-$150k')"
                    />
                    <InputError class="mt-2" :message="form.errors.salary_range" />
                </div>

                <div>
                    <label for="relevance_score" class="premium-input-label">{{ t('job_lead_form.relevance_score', 'Relevance score') }}</label>
                    <TextInput
                        id="relevance_score"
                        v-model="form.relevance_score"
                        type="number"
                        min="0"
                        max="100"
                        class="mt-2 block w-full"
                        :placeholder="t('job_lead_form.relevance_score_placeholder', '0 to 100')"
                    />
                    <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slateglass-400">
                        {{ t('job_lead_form.relevance_score_hint', 'Higher scores surface first in the discovery workspace.') }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.relevance_score" />
                </div>

                <div>
                    <label for="lead_status" class="premium-input-label">{{ t('job_lead_form.lead_status', 'Lead status') }}</label>
                    <select
                        id="lead_status"
                        v-model="form.lead_status"
                        class="mt-2 block w-full"
                    >
                        <option
                            v-for="leadStatus in leadStatuses"
                            :key="leadStatus"
                            :value="leadStatus"
                        >
                            {{ leadStatusLabel(leadStatus) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.lead_status" />
                </div>

                <div>
                    <label for="discovered_at" class="premium-input-label">{{ t('job_lead_form.discovered_at', 'Discovered at') }}</label>
                    <TextInput
                        id="discovered_at"
                        v-model="form.discovered_at"
                        type="date"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.discovered_at" />
                </div>
            </div>

            <div
                v-if="mode !== 'create'"
                id="job-description"
                class="mt-6 rounded-3xl border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-6"
                :class="focusDescription ? 'ring-1 ring-gold-300/40' : ''"
            >
                <div
                    v-if="focusDescription"
                    class="mb-4 rounded-2xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-100"
                >
                    {{ t('job_lead_edit.focus_description_hint', 'Paste the full job description here to improve deterministic matching for this lead.') }}
                </div>
                <label for="description_text" class="premium-input-label">{{ t('job_lead_form.description_text_edit', 'Job description (ATS analysis)') }}</label>
                <p class="mt-2 text-sm text-slateglass-300">
                    {{ t('job_lead_form.description_text_edit_description', 'Optional. Paste the full job description to extract keywords and optimize your resume.') }}
                </p>
                <textarea
                    id="description_text"
                    ref="descriptionTextarea"
                    v-model="form.description_text"
                    rows="10"
                    class="mt-4 block w-full"
                    :placeholder="t('job_lead_form.description_text_edit_placeholder', 'Paste the full job description here to unlock ATS insights for this lead.')"
                />
                <InputError class="mt-2" :message="form.errors.description_text" />
                <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slateglass-400">
                    {{ t('job_lead_form.url_only_warning', 'URL-only intake does not parse job pages automatically yet.') }}
                </p>
            </div>

            <div class="mt-6 rounded-3xl border border-white/10 bg-black/20 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <label for="description_excerpt" class="premium-input-label">{{ t('job_lead_form.personal_notes', 'Personal notes') }}</label>
                        <p class="mt-2 text-sm text-slateglass-400">
                            {{ t('job_lead_form.personal_notes_description', 'Optional. Keep your private observations separate from the ATS analysis input.') }}
                        </p>
                    </div>
                    <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400">
                        {{ t('job_lead_form.notes_badge', 'Notes') }}
                    </span>
                </div>
                <textarea
                    id="description_excerpt"
                    v-model="form.description_excerpt"
                    rows="5"
                    class="mt-4 block w-full"
                    :placeholder="t('job_lead_form.notes_placeholder', 'Capture the key signals, concerns, and follow-ups that matter to you.')"
                />
                <InputError class="mt-2" :message="form.errors.description_excerpt" />
            </div>
        </details>

        <div class="flex flex-wrap items-center gap-4 border-t border-white/10 pt-6">
            <button
                type="submit"
                class="premium-button-primary"
                :disabled="form.processing"
            >
                {{ submitLabel }}
            </button>
            <p
                v-if="form.hasErrors"
                class="text-sm text-red-300"
            >
                {{ t('job_lead_form.review_fields', 'Please review the highlighted fields.') }}
            </p>
        </div>
    </form>
</template>
