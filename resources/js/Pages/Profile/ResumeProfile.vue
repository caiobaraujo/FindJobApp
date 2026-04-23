<script setup>
import ResumeSkillsCard from '@/Components/ResumeSkillsCard.vue';
import UserProfileForm from '@/Components/UserProfileForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import { useI18n } from '@/composables/useI18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    hasResumeProfile: {
        type: Boolean,
        required: true,
    },
    detectedResumeSkills: {
        type: Array,
        required: true,
    },
    userProfile: {
        type: Object,
        default: null,
    },
});

const { t } = useI18n();

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
    if (props.userProfile) {
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        })).post(route('resume-profile.update'), {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
        return;
    }

    form.post(route('resume-profile.store'), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="t('resume.setup_title', 'Resume setup')" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    :eyebrow="t('resume.eyebrow', 'Resume-first setup')"
                    :title="t('resume.setup_title', 'Resume setup')"
                    :description="
                        t(
                            'resume.setup_description',
                            'Upload your resume first. The app uses it to detect overlap, show matched jobs, and prepare future tailored resume workflows.',
                        )
                    "
                >
                    <Link
                        :href="route('matched-jobs.index')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.view_matched_jobs', 'View matched jobs') }}
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <ResumeSkillsCard :skills="detectedResumeSkills" />

            <SectionCard
                :title="t('resume.upload_title', 'Upload your resume')"
                :description="
                    t(
                        'resume.upload_description',
                        'Resume upload is the primary setup step. Pasted text stays available as a fallback.',
                    )
                "
            >
                <EmptyState
                    v-if="!hasResumeProfile"
                    :title="t('resume.no_setup_title', 'No resume setup yet')"
                    :description="
                        t(
                            'resume.no_setup_description',
                            'Upload your resume to start automatic matching. No resume yet? Create one.',
                        )
                    "
                />

                <div class="mb-6 flex flex-wrap gap-3">
                    <Link
                        :href="route('resume-profile.create')"
                        class="premium-button-secondary"
                    >
                        {{ t('buttons.create_resume', 'Create resume') }}
                    </Link>
                </div>

                <UserProfileForm
                    :form="form"
                    :saved-resume="userProfile?.uploaded_resume ?? null"
                    mode="upload"
                    :submit-label="
                        hasResumeProfile
                            ? t('resume.update_setup', 'Update resume setup')
                            : t('resume.save_setup', 'Save resume setup')
                    "
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
