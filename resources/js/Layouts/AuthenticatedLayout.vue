<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const navigationItems = [
    {
        label: 'Dashboard',
        routeName: 'dashboard',
        active: 'dashboard',
        tone: 'secondary',
    },
    {
        label: 'Job Leads',
        routeName: 'job-leads.index',
        active: 'job-leads.index',
        tone: 'primary',
    },
    {
        label: 'Import Job',
        routeName: 'job-leads.import.entry',
        active: 'job-leads.import.entry',
        tone: 'secondary',
    },
    {
        label: 'Applications',
        routeName: 'applications.index',
        active: 'applications.*',
        tone: 'secondary',
    },
];
</script>

<template>
    <div class="min-h-screen">
        <div class="mx-auto max-w-7xl px-4 pt-5 sm:px-6 lg:px-8">
            <nav class="premium-panel px-5 py-4 sm:px-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-6">
                        <Link
                            :href="route('dashboard')"
                            class="flex items-center gap-3"
                        >
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-gold-400/20 bg-gold-400/10">
                                <ApplicationLogo class="h-6 w-6 fill-current text-gold-300" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                                    FindJobApp
                                </p>
                                <p class="text-sm text-slateglass-400">
                                    Discovery-first job search workspace
                                </p>
                            </div>
                        </Link>

                        <div class="hidden items-center gap-2 md:flex">
                            <Link
                                v-for="item in navigationItems"
                                :key="item.routeName"
                                :href="route(item.routeName)"
                                class="rounded-full px-4 py-2 text-sm font-medium transition"
                                :class="route().current(item.active)
                                    ? 'bg-gold-400/15 text-gold-300'
                                    : item.tone === 'primary'
                                        ? 'border border-gold-300/20 bg-gold-300/8 text-gold-200 hover:bg-gold-300/12'
                                        : 'text-slateglass-300 hover:bg-white/5 hover:text-white'"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>

                    <div class="hidden items-center gap-3 md:flex">
                        <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-right">
                            <p class="text-sm font-medium text-white">
                                {{ $page.props.auth.user.name }}
                            </p>
                            <p class="text-xs text-slateglass-400">
                                {{ $page.props.auth.user.email }}
                            </p>
                        </div>

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="premium-button-secondary"
                                >
                                    Account
                                    <svg
                                        class="ml-2 h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <button
                        type="button"
                        class="premium-button-secondary md:hidden"
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                    >
                        Menu
                    </button>
                </div>

                <div
                    v-if="showingNavigationDropdown"
                    class="mt-4 space-y-4 border-t border-white/10 pt-4 md:hidden"
                >
                    <div class="grid gap-2">
                        <Link
                            v-for="item in navigationItems"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            class="rounded-2xl px-4 py-3 text-sm font-medium transition"
                            :class="route().current(item.active)
                                ? 'bg-gold-400/15 text-gold-300'
                                : item.tone === 'primary'
                                    ? 'border border-gold-300/20 bg-gold-300/8 text-gold-200'
                                    : 'bg-white/5 text-slateglass-300 hover:text-white'"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm font-medium text-white">
                            {{ $page.props.auth.user.name }}
                        </p>
                        <p class="mt-1 text-xs text-slateglass-400">
                            {{ $page.props.auth.user.email }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <Link
                                :href="route('profile.edit')"
                                class="premium-button-secondary"
                            >
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="premium-button-secondary"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <header
            v-if="$slots.header"
            class="pt-8"
        >
            <slot name="header" />
        </header>

        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div
                v-if="$page.props.flash.success"
                class="premium-panel border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"
            >
                {{ $page.props.flash.success }}
            </div>
            <div
                v-if="$page.props.flash.error"
                class="premium-panel mt-3 border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-100"
            >
                {{ $page.props.flash.error }}
            </div>
        </div>

        <main>
            <slot />
        </main>
    </div>
</template>
