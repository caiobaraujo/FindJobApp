<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { usePage } from '@inertiajs/vue3';

defineEmits(['submit']);

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    savedResume: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        default: 'upload',
    },
    submitLabel: {
        type: String,
        required: true,
    },
});

const page = usePage();

function setResumeFile(event) {
    const [file] = event.target.files || [];
    props.form.resume_file = file ?? null;
}

function t(path, fallback) {
    const value = path.split('.').reduce((carry, key) => carry?.[key], page.props.translations);

    return value ?? fallback ?? path;
}

function formattedFileSize(size) {
    if (typeof size !== 'number' || Number.isNaN(size) || size <= 0) {
        return null;
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div
            v-if="mode === 'upload'"
            class="rounded-[2rem] border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-6"
        >
            <label for="resume_file" class="premium-input-label">{{ t('resume.upload_primary_title', 'Upload your resume') }}</label>
            <p class="mt-3 text-sm leading-7 text-slateglass-300">
                {{ t('resume.upload_primary_description', 'Upload your resume file to start matching jobs automatically. TXT files can be read immediately. PDF, DOC, and DOCX are stored now and ready for future parsing.') }}
            </p>

            <div
                v-if="savedResume"
                class="mt-5 rounded-3xl border border-emerald-400/15 bg-emerald-400/[0.06] px-5 py-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">
                            Uploaded
                        </p>
                        <p class="mt-2 text-sm font-medium text-white">
                            {{ savedResume.filename }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs uppercase tracking-[0.18em] text-emerald-200/80">
                        <span
                            v-if="formattedFileSize(savedResume.size)"
                            class="rounded-full border border-emerald-400/20 bg-black/20 px-3 py-1"
                        >
                            {{ formattedFileSize(savedResume.size) }}
                        </span>
                        <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1">
                            Saved
                        </span>
                    </div>
                </div>
            </div>

            <input
                id="resume_file"
                type="file"
                accept=".pdf,.doc,.docx,.txt"
                class="mt-4 block w-full"
                @change="setResumeFile"
            >
            <InputError class="mt-2" :message="form.errors.resume_file" />

            <details class="mt-6 rounded-3xl border border-white/10 bg-black/20 p-5">
                <summary class="cursor-pointer text-sm font-medium text-slateglass-200">
                    {{ t('resume.paste_fallback_toggle', 'Paste resume text instead') }}
                </summary>
                <div class="mt-4">
                    <label for="base_resume_text" class="premium-input-label">{{ t('resume.paste_fallback_label', 'Resume text fallback') }}</label>
                    <textarea
                        id="base_resume_text"
                        v-model="form.base_resume_text"
                        rows="12"
                        class="mt-2 block w-full"
                        :placeholder="t('resume.paste_fallback_placeholder', 'Paste your current resume text here if you do not want to upload a file yet.')"
                    />
                    <InputError class="mt-2" :message="form.errors.base_resume_text" />
                </div>
            </details>
        </div>

        <div
            v-else
            class="rounded-[2rem] border border-gold-300/15 bg-gradient-to-br from-gold-400/8 via-white/[0.03] to-transparent p-6"
        >
            <label for="base_resume_text" class="premium-input-label">{{ t('resume.create_primary_title', 'Create your resume') }}</label>
            <p class="mt-3 text-sm leading-7 text-slateglass-300">
                {{ t('resume.create_primary_description', 'Start with the main resume text. You can keep this simple and improve it later.') }}
            </p>
            <textarea
                id="base_resume_text"
                v-model="form.base_resume_text"
                rows="16"
                class="mt-4 block w-full"
                :placeholder="t('resume.create_primary_placeholder', 'Write or paste the base resume text you want the app to match against jobs.')"
            />
            <InputError class="mt-2" :message="form.errors.base_resume_text" />
        </div>

        <details class="rounded-[2rem] border border-white/10 bg-white/[0.03] p-6">
            <summary class="cursor-pointer list-none">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slateglass-400">
                            {{ t('resume.optional_details', 'Optional details') }}
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-white">
                            {{ t('resume.optional_details_title', 'Add skills and background context') }}
                        </h3>
                    </div>
                    <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                        {{ t('resume.secondary', 'Secondary') }}
                    </span>
                </div>
            </summary>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div>
                    <label for="target_role" class="premium-input-label">{{ t('resume.target_role', 'Target role') }}</label>
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
                    <label for="core_skills" class="premium-input-label">{{ t('resume.core_skills', 'Core skills') }}</label>
                    <textarea
                        id="core_skills"
                        v-model="form.core_skills"
                        rows="4"
                        class="mt-2 block w-full"
                        placeholder="Laravel, Vue, SQL, AWS"
                    />
                    <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slateglass-400">
                        {{ t('resume.core_skills_helper', 'Separate skills with commas or new lines.') }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.core_skills" />
                </div>

                <div class="md:col-span-2">
                    <label for="professional_summary" class="premium-input-label">{{ t('resume.professional_summary', 'Professional summary') }}</label>
                    <textarea
                        id="professional_summary"
                        v-model="form.professional_summary"
                        rows="5"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.professional_summary" />
                </div>

                <div>
                    <label for="work_experience_text" class="premium-input-label">{{ t('resume.work_experience', 'Work experience') }}</label>
                    <textarea
                        id="work_experience_text"
                        v-model="form.work_experience_text"
                        rows="8"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.work_experience_text" />
                </div>

                <div>
                    <label for="education_text" class="premium-input-label">{{ t('resume.education', 'Education') }}</label>
                    <textarea
                        id="education_text"
                        v-model="form.education_text"
                        rows="8"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.education_text" />
                </div>

                <div>
                    <label for="certifications_text" class="premium-input-label">{{ t('resume.certifications', 'Certifications') }}</label>
                    <textarea
                        id="certifications_text"
                        v-model="form.certifications_text"
                        rows="6"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.certifications_text" />
                </div>

                <div>
                    <label for="languages_text" class="premium-input-label">{{ t('resume.languages', 'Languages') }}</label>
                    <textarea
                        id="languages_text"
                        v-model="form.languages_text"
                        rows="6"
                        class="mt-2 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.languages_text" />
                </div>
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
            <p class="text-sm text-slateglass-400">
                {{ t('resume.footer_hint', 'Resume setup powers automatic job matching now and tailored resume workflows later.') }}
            </p>
        </div>
    </form>
</template>
