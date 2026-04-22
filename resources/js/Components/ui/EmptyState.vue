<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="px-6 py-14 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-gold-400/20 bg-gold-400/10 text-gold-300">
            <slot name="icon">
                <span class="text-xl">+</span>
            </slot>
        </div>
        <h3 class="mt-6 text-xl font-semibold text-white">
            {{ title }}
        </h3>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slateglass-400">
            {{ description }}
        </p>
        <div class="mt-6 flex justify-center">
            <slot />
        </div>
    </div>
</template>
