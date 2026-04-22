<script setup>
import UserProfileForm from '@/Components/UserProfileForm.vue';
import AppShell from '@/Components/ui/AppShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    userProfile: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    target_role: props.userProfile?.target_role ?? '',
    professional_summary: props.userProfile?.professional_summary ?? '',
    core_skills: props.userProfile?.core_skills?.join(', ') ?? '',
    work_experience_text: props.userProfile?.work_experience_text ?? '',
    education_text: props.userProfile?.education_text ?? '',
    certifications_text: props.userProfile?.certifications_text ?? '',
    languages_text: props.userProfile?.languages_text ?? '',
    base_resume_text: props.userProfile?.base_resume_text ?? '',
});

function submit() {
    if (props.userProfile) {
        form.patch(route('resume-profile.update'));
        return;
    }

    form.post(route('resume-profile.store'));
}
</script>

<template>
    <Head title="Resume Profile" />

    <AuthenticatedLayout>
        <template #header>
            <AppShell>
                <PageHeader
                    eyebrow="Resume optimization"
                    title="Resume profile"
                    description="Centralize your baseline resume content so each job lead can be matched against the background and skills you already have."
                >
                    <Link
                        :href="route('job-leads.index')"
                        class="premium-button-secondary"
                    >
                        Back to job leads
                    </Link>
                </PageHeader>
            </AppShell>
        </template>

        <AppShell>
            <SectionCard
                title="Base resume profile"
                description="This profile powers deterministic ATS matching now and will support tailored resumes later."
            >
                <EmptyState
                    v-if="!userProfile"
                    title="No resume profile yet"
                    description="Add your target role, skills, and base resume text so job leads can be matched against your current background."
                />

                <UserProfileForm
                    :form="form"
                    :submit-label="userProfile ? 'Update resume profile' : 'Create resume profile'"
                    @submit="submit"
                />
            </SectionCard>
        </AppShell>
    </AuthenticatedLayout>
</template>
