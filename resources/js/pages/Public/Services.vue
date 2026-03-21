<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';

const services = [
    {
        title: 'Account Onboarding',
        description: 'Create an account, submit required files, and wait for approval before full access.',
    },
    {
        title: 'Team Management',
        description: 'Assign players and coaches to teams so everyone is connected to the correct roster.',
    },
    {
        title: 'Schedule Management',
        description: 'Post practice and game schedules so students and coaches always see the latest plan.',
    },
    {
        title: 'Attendance Verification',
        description: 'Verify who attended sessions and keep clear attendance records for each schedule.',
    },
    {
        title: 'Wellness Monitoring',
        description: 'Record post-session condition updates to monitor fatigue, injuries, and readiness.',
    },
    {
        title: 'Academic Eligibility',
        description: 'Track academic submissions and eligibility status for each student-athlete.',
    },
    {
        title: 'Announcements & Alerts',
        description: 'Send important updates to users so they do not miss schedule and status changes.',
    },
    {
        title: 'Email Notifications',
        description: 'Deliver approval and system updates through email for faster user communication.',
    },
    {
        title: 'Reports & Printing',
        description: 'Generate printable summaries for attendance, wellness, and academic monitoring.',
    },
];
</script>

<template>
    <PublicLayout
        title="Services"
        page-title="What You Can Do in AC-VMIS"
        page-description="Each feature is built to help student-athletes and coaches complete daily varsity tasks faster."
    >
        <section class="services-grid">
            <article v-for="service in services" :key="service.title" class="public-card reveal">
                <h2 class="section-title">{{ service.title }}</h2>
                <p>{{ service.description }}</p>
            </article>
        </section>
    </PublicLayout>
</template>

<style scoped>
.services-grid {
    display: grid;
    gap: 0.75rem;
}

.public-card {
    border:  1px solid rgba(15, 23, 42, 0.35);
    border-radius: 14px;
    background: #ffffff;
    padding: 1rem;
    color: #475569;
}

.section-title {
    margin-bottom: 0.35rem;
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
}

@media (min-width: 768px) {
    .services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
