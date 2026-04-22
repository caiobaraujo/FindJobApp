<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: 'Workspace',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                {{ eyebrow }}
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-3 max-w-2xl text-sm leading-6 text-slateglass-300"
            >
                {{ description }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <slot />
        </div>
    </div>
</template>
