<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <slot name="hero">
                <div
                    v-if="title || subtitle"
                    class="premium-hero px-6 py-8 sm:px-8"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                        FindJobApp
                    </p>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mt-3 max-w-2xl text-sm leading-6 text-slateglass-300 sm:text-base"
                    >
                        {{ subtitle }}
                    </p>
                    <slot name="actions" />
                </div>
            </slot>

            <slot />
        </div>
    </section>
</template>
