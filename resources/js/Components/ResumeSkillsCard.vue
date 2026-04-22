<script setup>
defineProps({
    skills: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-[2rem] border border-white/10 bg-white/[0.03] p-6 shadow-panel">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold-300/80">
                    Matching input
                </p>
                <h3 class="mt-2 text-xl font-semibold text-white">
                    Detected resume skills
                </h3>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slateglass-300">
                    These skills are pulled from your current resume setup and power job matching across the app.
                </p>
            </div>
            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slateglass-300">
                {{ skills.length }} detected
            </span>
        </div>

        <div
            v-if="skills.length > 0"
            class="mt-6 flex flex-wrap gap-2"
        >
            <span
                v-for="skill in skills"
                :key="skill"
                class="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-xs font-medium text-gold-200"
            >
                {{ skill }}
            </span>
        </div>

        <div
            v-else
            class="mt-6 rounded-3xl border border-white/10 bg-black/20 px-5 py-4 text-sm leading-6 text-slateglass-300"
        >
            Add resume text or core skills to unlock detected resume skills.
        </div>
    </div>
</template>
