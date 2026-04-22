<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-300/80">
                Create your workspace
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">
                Register
            </h1>
            <p class="mt-3 text-sm leading-6 text-slateglass-400">
                Start tracking applications with a cleaner, more deliberate workflow.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="name" class="premium-input-label">Name</label>

                <TextInput
                    id="name"
                    type="text"
                    class="mt-2 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <label for="email" class="premium-input-label">Email</label>

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full"
                    v-model="form.email"
                    required
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
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <label for="password_confirmation" class="premium-input-label">
                    Confirm password
                </label>

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-2 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="flex flex-col gap-4 pt-2">
                <button
                    type="submit"
                    class="premium-button-primary w-full"
                    :disabled="form.processing"
                >
                    Register
                </button>
            </div>

            <div class="border-t border-white/10 pt-5 text-sm text-slateglass-400">
                Already registered?
                <Link :href="route('login')" class="premium-link ml-1">
                    Log in
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
