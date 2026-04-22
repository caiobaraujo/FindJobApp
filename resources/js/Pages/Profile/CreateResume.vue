<script setup>
import UserProfileForm from '@/Components/UserProfileForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    userProfile: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const form = useForm({
    target_role: props.userProfile?.target_role ?? '',
    professional_summary: props.userProfile?.professional_summary ?? '',
    core_skills: props.userProfile?.core_skills?.join(', ') ?? '',
    work_experience_text: props.userProfile?.work_experience_text ?? '',
    education_text: props.userProfile?.education_text ?? '',
    certifications_text: props.userProfile?.certifications_text ?? '',
    languages_text: props.userProfile?.languages_text ?? '',
    base_resume_text: props.userProfile?.base_resume_text ?? '',
    resume_file: null,
});

function submit() {
    if (props.hasResumeProfile) {
        form.patch(route('resume-profile.update'));
        return;
    }

    form.post(route('resume-profile.store'));
}

function t(path, fallback) {
    const value = path.split('.').reduce((carry, key) => carry?.[key], page.props.translations);

    return value ?? fallback ?? path;
}
</script>

<template>
    <Head :title="t('resume.create_title', 'Create resume')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('resume.eyebrow', 'Resume-first setup')"
                    :title="t('resume.create_title', 'Create resume')"
                    :description="t('resume.create_description', 'No file yet? Build a simple resume draft here. This is the secondary path and still feeds the same matching engine.')"
                >
                    <Link
                        :href="route('resume-profile.show')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.upload_resume', 'Upload your resume') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                :title="t('resume.create_title', 'Create resume')"
                :description="t('resume.create_card_description', 'Fill the basics only. The goal is to get enough resume content into the system to start matching jobs quickly.')"
            >
                <UserProfileForm
                    :form="form"
                    mode="create"
                    :submit-label="props.hasResumeProfile
                        ? t('resume.update_setup', 'Update resume setup')
                        : t('resume.save_setup', 'Save resume setup')"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
