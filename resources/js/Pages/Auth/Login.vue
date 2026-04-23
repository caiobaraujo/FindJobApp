<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import { useI18n } from '@/composables/useI18n';
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

const { t } = useI18n();

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
        <Head :title="t('auth.login_title', 'Log in')" />

        <div v-if="status" class="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-100">
            {{ status }}
        </div>

        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                {{ t('auth.login_eyebrow', 'Welcome back') }}
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">
                {{ t('auth.login_title', 'Log in') }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-slateglass-400">
                {{ t('auth.login_description', 'Access your matched jobs, saved leads, and next follow-ups.') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="premium-input-label">{{ t('auth.email', 'Email') }}</label>

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
                <label for="password" class="premium-input-label">{{ t('auth.password', 'Password') }}</label>

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
                    <span class="ms-3 text-sm text-slateglass-300">{{ t('auth.remember_me', 'Remember me') }}</span>
                </label>
            </div>

            <div class="flex flex-col gap-4 pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="premium-link"
                >
                    {{ t('auth.forgot_password', 'Forgot your password?') }}
                </Link>

                <button
                    type="submit"
                    class="premium-button-primary w-full"
                    :disabled="form.processing"
                >
                    {{ t('auth.login_title', 'Log in') }}
                </button>
            </div>

            <div class="border-t border-white/10 pt-5 text-sm text-slateglass-400">
                {{ t('auth.new_here', 'New here?') }}
                <Link :href="route('register')" class="premium-link ml-1">
                    {{ t('auth.create_account', 'Create an account') }}
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
