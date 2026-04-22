<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

defineEmits(['submit']);

defineProps({
    form: {
        type: Object,
        required: true,
    },
    submitLabel: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="target_role" class="premium-input-label">Target role</label>
                <TextInput
                    id="target_role"
                    v-model="form.target_role"
                    type="text"
                    class="mt-2 block w-full"
                    placeholder="Senior Product Engineer"
                />
                <InputError class="mt-2" :message="form.errors.target_role" />
            </div>

            <div>
                <label for="core_skills" class="premium-input-label">Core skills</label>
                <textarea
                    id="core_skills"
                    v-model="form.core_skills"
                    rows="4"
                    class="mt-2 block w-full"
                    placeholder="Laravel, Vue, SQL, AWS"
                />
                <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slateglass-400">
                    Separate skills with commas or new lines.
                </p>
                <InputError class="mt-2" :message="form.errors.core_skills" />
            </div>
        </div>

        <div>
            <label for="professional_summary" class="premium-input-label">Professional summary</label>
            <textarea
                id="professional_summary"
                v-model="form.professional_summary"
                rows="5"
                class="mt-2 block w-full"
                placeholder="Summarize the positioning you want reflected across ATS-oriented resume tailoring."
            />
            <InputError class="mt-2" :message="form.errors.professional_summary" />
        </div>

        <div>
            <label for="base_resume_text" class="premium-input-label">Base resume text</label>
            <textarea
                id="base_resume_text"
                v-model="form.base_resume_text"
                rows="14"
                class="mt-2 block w-full"
                placeholder="Paste the current base resume text that future ATS optimization and tailored resumes should build from."
            />
            <InputError class="mt-2" :message="form.errors.base_resume_text" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="work_experience_text" class="premium-input-label">Work experience</label>
                <textarea
                    id="work_experience_text"
                    v-model="form.work_experience_text"
                    rows="8"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.work_experience_text" />
            </div>

            <div>
                <label for="education_text" class="premium-input-label">Education</label>
                <textarea
                    id="education_text"
                    v-model="form.education_text"
                    rows="8"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.education_text" />
            </div>

            <div>
                <label for="certifications_text" class="premium-input-label">Certifications</label>
                <textarea
                    id="certifications_text"
                    v-model="form.certifications_text"
                    rows="6"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.certifications_text" />
            </div>

            <div>
                <label for="languages_text" class="premium-input-label">Languages</label>
                <textarea
                    id="languages_text"
                    v-model="form.languages_text"
                    rows="6"
                    class="mt-2 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.languages_text" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 border-t border-white/10 pt-6">
            <button
                type="submit"
                class="premium-button-primary"
                :disabled="form.processing"
            >
                {{ submitLabel }}
            </button>
            <p class="text-sm text-slateglass-400">
                This profile powers deterministic ATS matching and future tailored resume workflows.
            </p>
        </div>
    </form>
</template>
