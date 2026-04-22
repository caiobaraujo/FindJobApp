<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

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
    workModes: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="space-y-6">
        <div class="rounded-3xl border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-6">
            <label for="description_text" class="premium-input-label">Job description (ATS analysis)</label>
            <p class="mt-2 text-sm text-slateglass-300">
                Paste the full job description to extract keywords and optimize your resume
            </p>
            <textarea
                id="description_text"
                v-model="form.description_text"
                rows="12"
                class="mt-4 block w-full"
                placeholder="Paste the full job description here to unlock ATS insights for this lead."
            />
            <InputError class="mt-2" :message="form.errors.description_text" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="company_name" class="premium-input-label">Company name</label>
                <TextInput
                    id="company_name"
                    v-model="form.company_name"
                    type="text"
                    class="mt-2 block w-full"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.company_name" />
            </div>

            <div>
                <label for="job_title" class="premium-input-label">Job title</label>
                <TextInput
                    id="job_title"
                    v-model="form.job_title"
                    type="text"
                    class="mt-2 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.job_title" />
            </div>

            <div>
                <label for="source_name" class="premium-input-label">Source name</label>
                <TextInput
                    id="source_name"
                    v-model="form.source_name"
                    type="text"
                    class="mt-2 block w-full"
                    placeholder="LinkedIn, Indeed, company site"
                />
                <InputError class="mt-2" :message="form.errors.source_name" />
            </div>

            <div>
                <label for="source_url" class="premium-input-label">Source URL</label>
                <TextInput
                    id="source_url"
                    v-model="form.source_url"
                    type="url"
                    class="mt-2 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.source_url" />
            </div>

            <div>
                <label for="location" class="premium-input-label">Location</label>
                <TextInput
                    id="location"
                    v-model="form.location"
                    type="text"
                    class="mt-2 block w-full"
                    placeholder="New York, NY"
                />
                <InputError class="mt-2" :message="form.errors.location" />
            </div>

            <div>
                <label for="work_mode" class="premium-input-label">Work mode</label>
                <select
                    id="work_mode"
                    v-model="form.work_mode"
                    class="mt-2 block w-full"
                >
                    <option value="">Not set</option>
                    <option
                        v-for="workMode in workModes"
                        :key="workMode"
                        :value="workMode"
                    >
                        {{ workMode }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.work_mode" />
            </div>

            <div>
                <label for="salary_range" class="premium-input-label">Salary range</label>
                <TextInput
                    id="salary_range"
                    v-model="form.salary_range"
                    type="text"
                    class="mt-2 block w-full"
                    placeholder="$120k-$150k"
                />
                <InputError class="mt-2" :message="form.errors.salary_range" />
            </div>

            <div>
                <label for="relevance_score" class="premium-input-label">Relevance score</label>
                <TextInput
                    id="relevance_score"
                    v-model="form.relevance_score"
                    type="number"
                    min="0"
                    max="100"
                    class="mt-2 block w-full"
                    placeholder="0 to 100"
                />
                <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slateglass-400">
                    Higher scores surface first in the discovery workspace.
                </p>
                <InputError class="mt-2" :message="form.errors.relevance_score" />
            </div>

            <div>
                <label for="lead_status" class="premium-input-label">Lead status</label>
                <select
                    id="lead_status"
                    v-model="form.lead_status"
                    class="mt-2 block w-full"
                    required
                >
                    <option
                        v-for="leadStatus in leadStatuses"
                        :key="leadStatus"
                        :value="leadStatus"
                    >
                        {{ leadStatus }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.lead_status" />
            </div>

            <div>
                <label for="discovered_at" class="premium-input-label">Discovered at</label>
                <TextInput
                    id="discovered_at"
                    v-model="form.discovered_at"
                    type="date"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.discovered_at" />
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <label for="description_excerpt" class="premium-input-label">Personal notes</label>
                    <p class="mt-2 text-sm text-slateglass-400">
                        Keep your private observations separate from the ATS analysis input.
                    </p>
                </div>
                <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-400">
                    Notes
                </span>
            </div>
            <textarea
                id="description_excerpt"
                v-model="form.description_excerpt"
                rows="5"
                class="mt-4 block w-full"
                placeholder="Capture the key signals, concerns, and follow-ups that matter to you."
            />
            <InputError class="mt-2" :message="form.errors.description_excerpt" />
        </div>

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
                Please review the highlighted fields.
            </p>
        </div>
    </form>
</template>
