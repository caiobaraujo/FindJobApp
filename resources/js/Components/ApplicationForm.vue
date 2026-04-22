<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

defineEmits(['submit']);

defineProps({
    form: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    submitLabel: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="space-y-6">
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
                <label for="status" class="premium-input-label">Status</label>
                <select
                    id="status"
                    v-model="form.status"
                    class="mt-2 block w-full"
                    required
                >
                    <option
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ status }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.status" />
            </div>

            <div>
                <label for="applied_at" class="premium-input-label">Applied at</label>
                <TextInput
                    id="applied_at"
                    v-model="form.applied_at"
                    type="date"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.applied_at" />
            </div>
        </div>

        <div>
            <label for="source_url" class="premium-input-label">Source URL</label>
            <TextInput
                id="source_url"
                v-model="form.source_url"
                type="url"
                class="mt-2 block w-full"
                placeholder="https://example.com/job-post"
            />
            <InputError class="mt-2" :message="form.errors.source_url" />
        </div>

        <div>
            <label for="notes" class="premium-input-label">Notes</label>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="5"
                class="mt-2 block w-full"
                placeholder="Add interview notes, salary expectations, or follow-up reminders."
            />
            <InputError class="mt-2" :message="form.errors.notes" />
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
