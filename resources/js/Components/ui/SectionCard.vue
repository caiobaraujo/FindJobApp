<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    padded: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <section class="premium-panel overflow-hidden">
        <div
            v-if="title || description || $slots.actions"
            class="flex flex-col gap-4 border-b border-white/10 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2
                    v-if="title"
                    class="text-lg font-semibold text-white"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-slateglass-400"
                >
                    {{ description }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <slot name="actions" />
            </div>
        </div>

        <div :class="padded ? 'p-6' : ''">
            <slot />
        </div>
    </section>
</template>
