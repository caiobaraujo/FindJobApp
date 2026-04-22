<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        required: true,
    },
    tone: {
        type: String,
        default: 'default',
    },
});

const toneClasses = {
    default: 'from-white/8 to-white/[0.02]',
    accent: 'from-gold-400/18 to-white/[0.02]',
};
</script>

<template>
    <div class="premium-panel-muted overflow-hidden p-5">
        <div
            class="rounded-[1.25rem] bg-gradient-to-br p-5"
            :class="toneClasses[tone] ?? toneClasses.default"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slateglass-400">
                {{ label }}
            </p>
            <p class="mt-4 text-3xl font-semibold tracking-tight text-white">
                {{ value }}
            </p>
            <slot />
        </div>
    </div>
</template>
