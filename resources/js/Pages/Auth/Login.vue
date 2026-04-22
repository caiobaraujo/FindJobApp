<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-100">
            {{ status }}
        </div>

        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                Welcome back
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">
                Log in
            </h1>
            <p class="mt-3 text-sm leading-6 text-slateglass-400">
                Access your application pipeline, recent momentum, and next follow-ups.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="premium-input-label">Email</label>

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <label for="password" class="premium-input-label">Password</label>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-2 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-3 text-sm text-slateglass-300">Remember me</span>
                </label>
            </div>

            <div class="flex flex-col gap-4 pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="premium-link"
                >
                    Forgot your password?
                </Link>

                <button
                    type="submit"
                    class="premium-button-primary w-full"
                    :disabled="form.processing"
                >
                    Log in
                </button>
            </div>

            <div class="border-t border-white/10 pt-5 text-sm text-slateglass-400">
                New here?
                <Link :href="route('register')" class="premium-link ml-1">
                    Create an account
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
