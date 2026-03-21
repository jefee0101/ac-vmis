<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';

const faqs = [
    {
        q: 'How do I register as a student-athlete?',
        a: 'Open Register, choose Student-Athlete, fill in required details, and upload the needed files.',
    },
    {
        q: 'How long does account approval take?',
        a: 'Approval time depends on review workload. Keep your documents complete to avoid delays.',
    },
    {
        q: 'How do I register as a coach?',
        a: 'Open Register, choose Coach, complete your profile, then wait for approval.',
    },
    {
        q: 'Where do I check schedules and attendance?',
        a: 'After login, use your schedule page to view sessions and attendance status.',
    },
    {
        q: 'What is Attendance Verification?',
        a: 'It records who attended each session and keeps attendance history per schedule.',
    },
    {
        q: 'What is Wellness Monitoring used for?',
        a: 'It tracks post-session condition updates such as fatigue and injury observations.',
    },
    {
        q: 'How does Academic Eligibility work?',
        a: 'Students submit academic documents per period, then reviewers post eligibility results.',
    },
    {
        q: 'Where can I ask for help?',
        a: 'Use the Contact page for official email, phone support, and office hours.',
    },
];
</script>

<template>
    <PublicLayout title="FAQ" page-title="Frequently Asked Questions" page-description="Quick answers for first-time student-athletes and coaches.">
        <section v-for="item in faqs" :key="item.q" class="public-card reveal">
            <h2 class="question">{{ item.q }}</h2>
            <p class="answer">{{ item.a }}</p>
        </section>
    </PublicLayout>
</template>

<style scoped>
.public-card {
    border:  1px solid rgba(15, 23, 42, 0.35);
    border-radius: 14px;
    background: #ffffff;
    padding: 1rem;
}

.question {
    font-size: 0.98rem;
    font-weight: 700;
    color: #1f2937;
}

.answer {
    margin-top: 0.45rem;
    color: #475569;
}
</style>
