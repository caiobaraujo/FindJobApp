<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
                <InputLabel for="company_name" value="Company name" />
                <TextInput
                    id="company_name"
                    v-model="form.company_name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.company_name" />
            </div>

            <div>
                <InputLabel for="job_title" value="Job title" />
                <TextInput
                    id="job_title"
                    v-model="form.job_title"
                    type="text"
                    class="mt-1 block w-full"
                    required
                />
                <InputError class="mt-2" :message="form.errors.job_title" />
            </div>

            <div>
                <InputLabel for="status" value="Status" />
                <select
                    id="status"
                    v-model="form.status"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                <InputLabel for="applied_at" value="Applied at" />
                <TextInput
                    id="applied_at"
                    v-model="form.applied_at"
                    type="date"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.applied_at" />
            </div>
        </div>

        <div>
            <InputLabel for="source_url" value="Source URL" />
            <TextInput
                id="source_url"
                v-model="form.source_url"
                type="url"
                class="mt-1 block w-full"
                placeholder="https://example.com/job-post"
            />
            <InputError class="mt-2" :message="form.errors.source_url" />
        </div>

        <div>
            <InputLabel for="notes" value="Notes" />
            <textarea
                id="notes"
                v-model="form.notes"
                rows="5"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Add interview notes, salary expectations, or follow-up reminders."
            />
            <InputError class="mt-2" :message="form.errors.notes" />
        </div>

        <div class="flex items-center gap-4">
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel }}
            </PrimaryButton>
            <p
                v-if="form.hasErrors"
                class="text-sm text-red-600"
            >
                Please review the highlighted fields.
            </p>
        </div>
    </form>
</template>
