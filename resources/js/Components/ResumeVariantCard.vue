<script setup>
import InputError from '@/Components/InputError.vue';
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    canGenerate: {
        type: Boolean,
        required: true,
    },
    jobLeadId: {
        type: Number,
        required: true,
    },
    modes: {
        type: Array,
        required: true,
    },
    requirements: {
        type: Object,
        required: true,
    },
    variants: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();
const generateForm = useForm({
    mode: props.modes[0]?.value ?? 'faithful',
});
const copiedVariantId = ref(null);

function generate() {
    generateForm.post(route('job-leads.resume-variants.store', props.jobLeadId), {
        preserveScroll: true,
    });
}

async function copyVariant(variant) {
    try {
        await navigator.clipboard.writeText(variant.generated_text);
        copiedVariantId.value = variant.id;
        window.setTimeout(() => {
            if (copiedVariantId.value === variant.id) {
                copiedVariantId.value = null;
            }
        }, 1600);
    } catch {
        copiedVariantId.value = null;
    }
}

function modeLabel(mode) {
    return props.modes.find((candidate) => candidate.value === mode)?.label ?? mode;
}
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                        {{ t('resume_variants.eyebrow', 'Tailored resume') }}
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-white">
                        {{ t('resume_variants.title', 'Generate an ATS-focused resume variant') }}
                    </h3>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slateglass-300">
                        {{ t('resume_variants.description', 'Use your existing resume plus this job description to generate a plain-text resume version for this role. The mode controls how strictly the output stays inside your current experience.') }}
                    </p>
                </div>
            </div>

            <form
                class="mt-6 space-y-4"
                @submit.prevent="generate"
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <label
                        v-for="mode in modes"
                        :key="mode.value"
                        class="rounded-3xl border p-4 transition"
                        :class="generateForm.mode === mode.value
                            ? 'border-gold-300/40 bg-gold-300/10'
                            : 'border-white/10 bg-black/20 hover:border-white/20'"
                    >
                        <input
                            v-model="generateForm.mode"
                            type="radio"
                            name="resume_variant_mode"
                            class="sr-only"
                            :value="mode.value"
                        >
                        <p class="text-sm font-semibold text-white">
                            {{ mode.label }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slateglass-300">
                            {{ mode.description }}
                        </p>
                    </label>
                </div>

                <div class="rounded-3xl border border-white/10 bg-black/20 p-4 text-sm leading-7 text-slateglass-300">
                    <p>
                        {{ t('resume_variants.requirements_intro', 'Generation requires both your base resume text and the full job description.') }}
                    </p>
                    <ul class="mt-3 space-y-2">
                        <li>
                            {{ requirements.has_resume_text
                                ? t('resume_variants.requirement_resume_ready', 'Base resume: ready')
                                : t('resume_variants.requirement_resume_missing', 'Base resume: missing') }}
                        </li>
                        <li>
                            {{ requirements.has_job_description
                                ? t('resume_variants.requirement_job_ready', 'Job description: ready')
                                : t('resume_variants.requirement_job_missing', 'Job description: missing') }}
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="premium-button-primary"
                        :disabled="generateForm.processing || !canGenerate"
                    >
                        {{ generateForm.processing
                            ? t('resume_variants.generating', 'Generating...')
                            : t('resume_variants.generate_button', 'Generate tailored resume') }}
                    </button>
                    <p class="text-xs uppercase tracking-[0.16em] text-slateglass-400">
                        {{ t('resume_variants.plain_text_note', 'Plain text only. No invented experience.') }}
                    </p>
                </div>

                <InputError
                    class="mt-2"
                    :message="generateForm.errors.mode"
                />
            </form>
        </div>

        <div
            v-if="variants.length === 0"
            class="rounded-3xl border border-dashed border-white/10 bg-black/20 p-6 text-sm leading-7 text-slateglass-300"
        >
            {{ t('resume_variants.empty', 'No tailored resume generated yet.') }}
        </div>

        <div
            v-for="variant in variants"
            :key="variant.id"
            class="rounded-3xl border border-white/10 bg-white/[0.03] p-6"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                        {{ t('resume_variants.variant_label', 'Resume variant') }}
                    </p>
                    <h4 class="mt-2 text-lg font-semibold text-white">
                        {{ modeLabel(variant.mode) }}
                    </h4>
                    <p class="mt-2 text-xs uppercase tracking-[0.16em] text-slateglass-400">
                        {{ new Date(variant.created_at).toLocaleString() }}
                    </p>
                </div>

                <button
                    type="button"
                    class="premium-button-secondary"
                    @click="copyVariant(variant)"
                >
                    {{ copiedVariantId === variant.id
                        ? t('resume_variants.copied', 'Copied')
                        : t('resume_variants.copy', 'Copy') }}
                </button>
            </div>

            <textarea
                :value="variant.generated_text"
                rows="18"
                readonly
                class="mt-4 block w-full"
            />
        </div>
    </div>
</template>
