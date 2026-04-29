<script setup>
import ResumeDiscoverySignalsCard from '@/Components/ResumeDiscoverySignalsCard.vue';
import UserProfileForm from '@/Components/UserProfileForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import { useI18n } from '@/composables/useI18n';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    resumeDiscoverySignals: {
        type: Object,
        required: true,
    },
    userProfile: {
        type: Object,
        default: null,
    },
    workModes: {
        type: Array,
        required: true,
    },
});

const { t } = useI18n();

const form = useForm({
    target_role: props.userProfile?.target_role ?? '',
    target_roles: props.userProfile?.target_roles?.join(', ') ?? '',
    preferred_locations: props.userProfile?.preferred_locations?.join('\n') ?? '',
    preferred_work_modes: props.userProfile?.preferred_work_modes ?? [],
    auto_discover_jobs: Boolean(props.userProfile?.auto_discover_jobs),
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
            <ResumeDiscoverySignalsCard :signals="resumeDiscoverySignals" />

            <SectionCard
                class="mt-6"
                :title="t('resume.create_title', 'Create resume')"
                :description="t('resume.create_card_description', 'Fill the basics only. The goal is to get enough resume content into the system to start matching jobs quickly.')"
            >
                <UserProfileForm
                    :form="form"
                    :work-modes="props.workModes"
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
