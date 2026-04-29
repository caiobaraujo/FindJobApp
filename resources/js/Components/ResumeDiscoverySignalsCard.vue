<script setup>
import { useI18n } from '@/composables/useI18n';

defineProps({
    signals: {
        type: Object,
        required: true,
    },
});

const { t } = useI18n();
</script>

<template>
    <div class="rounded-[2rem] border border-white/10 bg-white/[0.03] p-6 shadow-panel">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold-300/80">
                    {{ t('resume_signals.eyebrow', 'Discovery input') }}
                </p>
                <h3 class="mt-2 text-xl font-semibold text-white">
                    {{ t('resume_signals.title', 'Resume-derived discovery signals') }}
                </h3>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slateglass-300">
                    {{ t('resume_signals.description', 'These deterministic signals expand observed resume terms into inspectable discovery-oriented families, aliases, and query profiles.') }}
                </p>
            </div>
            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                {{ signals.query_profiles.length }} {{ t('resume_signals.profiles', 'profiles') }}
            </span>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-black/20 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                    {{ t('resume_signals.role_families', 'Role families') }}
                </p>
                <div v-if="signals.role_families.length" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="family in signals.role_families"
                        :key="family"
                        class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200"
                    >
                        {{ family }}
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-black/20 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                    {{ t('resume_signals.canonical_skills', 'Canonical skills') }}
                </p>
                <div v-if="signals.canonical_skills.length" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="skill in signals.canonical_skills"
                        :key="skill"
                        class="rounded-full border border-sky-400/20 bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-200"
                    >
                        {{ skill }}
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-black/20 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                    {{ t('resume_signals.aliases', 'Alias terms') }}
                </p>
                <div v-if="signals.aliases.length" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="alias in signals.aliases"
                        :key="alias"
                        class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
                    >
                        {{ alias }}
                    </span>
                </div>
            </div>
        </div>

        <div
            v-if="signals.query_profiles.length"
            class="mt-6 space-y-3"
        >
            <div
                v-for="profile in signals.query_profiles"
                :key="profile.key"
                class="rounded-3xl border border-white/10 bg-black/20 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-white">{{ profile.label }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slateglass-400">{{ profile.key }}</p>
                    </div>
                    <code class="rounded-full border border-white/10 bg-black/30 px-3 py-1 text-xs text-slateglass-200">{{ profile.query }}</code>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="signal in profile.signals"
                        :key="`${profile.key}-${signal}`"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-white"
                    >
                        {{ signal }}
                    </span>
                </div>

                <p class="mt-3 text-xs text-slateglass-400">
                    {{ t('resume_signals.aliases_label', 'Aliases') }}: {{ profile.aliases.join(', ') }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="mt-6 rounded-3xl border border-white/10 bg-black/20 px-5 py-4 text-sm leading-6 text-slateglass-300"
        >
            {{ t('resume_signals.empty', 'Add resume text or core skills to derive discovery-oriented query profiles.') }}
        </div>
    </div>
</template>
