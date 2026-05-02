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
const copiedAction = ref('');
const sectionDefinitions = [
    { title: 'Summary', labelKey: 'resume_variants.sections.summary' },
    { title: 'Core Skills', labelKey: 'resume_variants.sections.core_skills' },
    { title: 'Professional Experience', labelKey: 'resume_variants.sections.professional_experience' },
    { title: 'Target Role Alignment', labelKey: 'resume_variants.sections.target_role_alignment' },
];
const sectionOrder = sectionDefinitions.map((section) => section.title);

function generate() {
    generateForm.post(route('job-leads.resume-variants.store', props.jobLeadId), {
        preserveScroll: true,
    });
}

async function copyVariant(variant) {
    try {
        await navigator.clipboard.writeText(variant.generated_text);
        copiedAction.value = `${variant.id}:full`;
        clearCopiedActionLater(variant.id, 'full');
    } catch {
        copiedAction.value = '';
    }
}

async function copySummary(variant) {
    const summary = sectionText(variant.generated_text, 'Summary');

    if (! summary) {
        return;
    }

    try {
        await navigator.clipboard.writeText(summary);
        copiedAction.value = `${variant.id}:summary`;
        clearCopiedActionLater(variant.id, 'summary');
    } catch {
        copiedAction.value = '';
    }
}

function clearCopiedActionLater(variantId, scope) {
    window.setTimeout(() => {
        if (copiedAction.value === `${variantId}:${scope}`) {
            copiedAction.value = '';
        }
    }, 1600);
}

function modeLabel(mode) {
    return props.modes.find((candidate) => candidate.value === mode)?.label ?? mode;
}

function formatSectionTitle(title) {
    const section = sectionDefinitions.find((candidate) => candidate.title === title);

    return section ? t(section.labelKey, title) : title;
}

function generationErrorMessages() {
    return [
        t('resume_variants.unavailable'),
        t('resume_variants.unavailable_model'),
        t('resume_variants.generation_failed'),
    ];
}

function isUnavailableVariant(variant) {
    return generationErrorMessages().includes(String(variant.generated_text ?? '').trim());
}

function normalizeHeading(line) {
    return line.trim().replace(/:$/, '').toLowerCase();
}

function sectionText(text, title) {
    const sections = sectionBlocks(text);
    const section = sections.find((candidate) => candidate.title === title);

    return section?.body ?? '';
}

function sectionBlocks(text) {
    const lines = String(text ?? '').replace(/\r\n/g, '\n').split('\n');
    const sections = [];
    let currentTitle = null;
    let buffer = [];

    const pushSection = () => {
        if (! currentTitle) {
            buffer = [];
            return;
        }

        sections.push({
            title: currentTitle,
            body: buffer.join('\n').trim(),
        });
        buffer = [];
    };

    for (const line of lines) {
        const heading = sectionOrder.find((candidate) => normalizeHeading(line) === normalizeHeading(candidate));

        if (heading) {
            pushSection();
            currentTitle = heading;
            continue;
        }

        if (currentTitle) {
            buffer.push(line);
        }
    }

    pushSection();

    return sectionOrder.map((title) => sections.find((section) => section.title === title) ?? {
        title,
        body: '',
    });
}

function bodyLines(body) {
    return String(body ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                        {{ t('resume_variants.eyebrow') }}
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-white">
                        {{ t('resume_variants.title') }}
                    </h3>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slateglass-300">
                        {{ t('resume_variants.description') }}
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
                        {{ t('resume_variants.requirements_intro') }}
                    </p>
                    <ul class="mt-3 space-y-2">
                        <li>
                            {{ requirements.has_resume_text
                                ? t('resume_variants.requirement_resume_ready')
                                : t('resume_variants.requirement_resume_missing') }}
                        </li>
                        <li>
                            {{ requirements.has_job_description
                                ? t('resume_variants.requirement_job_ready')
                                : t('resume_variants.requirement_job_missing') }}
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
                            ? t('resume_variants.generating')
                            : t('resume_variants.generate_button') }}
                    </button>
                    <p class="text-xs uppercase tracking-[0.16em] text-slateglass-400">
                        {{ t('resume_variants.plain_text_note') }}
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
            {{ t('resume_variants.empty') }}
        </div>

        <div
            v-for="variant in variants"
            :key="variant.id"
            class="rounded-3xl border border-white/10 bg-white/[0.03] p-6"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gold-300/80">
                        {{ t('resume_variants.variant_label') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h4 class="text-lg font-semibold text-white">
                            {{ t('resume_variants.generated_title') }}
                        </h4>
                        <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                            {{ modeLabel(variant.mode) }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs uppercase tracking-[0.16em] text-slateglass-400">
                        {{ new Date(variant.created_at).toLocaleString() }}
                    </p>
                </div>

                <div
                    v-if="!isUnavailableVariant(variant)"
                    class="flex flex-wrap gap-3"
                >
                    <button
                        type="button"
                        class="premium-button-secondary"
                        @click="copySummary(variant)"
                    >
                        {{ copiedAction === `${variant.id}:summary`
                            ? t('resume_variants.summary_copied')
                            : t('resume_variants.copy_summary') }}
                    </button>
                    <button
                        type="button"
                        class="premium-button-secondary"
                        @click="copyVariant(variant)"
                    >
                        {{ copiedAction === `${variant.id}:full`
                            ? t('resume_variants.full_copied')
                            : t('resume_variants.copy_full') }}
                    </button>
                </div>
            </div>

            <div
                v-if="isUnavailableVariant(variant)"
                class="mt-5 rounded-3xl border border-amber-500/20 bg-amber-500/10 p-5 text-sm leading-7 text-amber-50"
            >
                {{ t('resume_variants.unavailable') }}
            </div>

            <div
                v-else
                class="mt-5 grid gap-4"
            >
                <section
                    v-for="section in sectionBlocks(variant.generated_text)"
                    :key="section.title"
                    class="rounded-3xl border border-white/10 bg-black/20 p-5"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h5 class="text-sm font-semibold uppercase tracking-[0.18em] text-gold-300/80">
                            {{ formatSectionTitle(section.title) }}
                        </h5>
                    </div>

                    <div
                        v-if="bodyLines(section.body).length > 0"
                        class="mt-4 whitespace-pre-line text-sm leading-7 text-slateglass-200"
                    >
                        {{ section.body }}
                    </div>

                    <p
                        v-else
                        class="mt-4 text-sm text-slateglass-400"
                    >
                        {{ t('resume_variants.section_missing') }}
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
