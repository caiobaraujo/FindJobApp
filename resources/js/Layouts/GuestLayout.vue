<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        <div class="pointer-events-none absolute inset-0 bg-premium-grid bg-[size:72px_72px] opacity-20" />
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-gold-400/10 to-transparent" />

        <div class="relative w-full max-w-5xl">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div class="hidden lg:block">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-gold-300/80">
                        Premium pipeline workspace
                    </p>
                    <h1 class="mt-5 max-w-xl text-5xl font-semibold tracking-tight text-white">
                        Track every opportunity with a sharper point of view.
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slateglass-300">
                        FindJobApp keeps your job search clean, structured, and visible in one premium workspace.
                    </p>
                    <div class="mt-10 grid max-w-lg grid-cols-3 gap-4">
                        <div class="premium-panel-muted p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slateglass-400">Focus</p>
                            <p class="mt-3 text-lg font-semibold text-white">Pipeline clarity</p>
                        </div>
                        <div class="premium-panel-muted p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slateglass-400">Signal</p>
                            <p class="mt-3 text-lg font-semibold text-white">Status visibility</p>
                        </div>
                        <div class="premium-panel-muted p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slateglass-400">Rhythm</p>
                            <p class="mt-3 text-lg font-semibold text-white">Fast follow-up</p>
                        </div>
                    </div>
                </div>

                <div class="premium-panel mx-auto w-full max-w-md overflow-hidden p-6 sm:p-8">
                    <div class="mb-8 flex items-center gap-4">
                        <Link href="/" class="flex items-center gap-3">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-gold-400/20 bg-gold-400/10">
                                <ApplicationLogo class="h-7 w-7 fill-current text-gold-300" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                                    FindJobApp
                                </p>
                                <p class="mt-1 text-sm text-slateglass-400">
                                    Premium application tracking
                                </p>
                            </div>
                        </Link>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
