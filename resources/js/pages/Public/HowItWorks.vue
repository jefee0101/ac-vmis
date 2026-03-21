<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';

const steps = [
    'Register your account based on your role (student-athlete or coach).',
    'Submit the required documents during signup.',
    'Wait for your account approval before full access.',
    'Use daily features for schedules, attendance, and wellness updates.',
    'Stay compliant by keeping academic and health requirements updated.',
];
</script>

<template>
    <PublicLayout
        title="How It Works"
        page-title="How AC-VMIS Works"
        page-description="Five simple steps from registration to daily varsity use."
    >
        <section class="public-card reveal">
            <ol class="space-y-2 text-sm text-[#1f2937]">
                <li v-for="(step, index) in steps" :key="step"><strong>{{ index + 1 }}.</strong> {{ step }}</li>
            </ol>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">After Approval</h2>
            <p>
                Log in and use your role-based pages right away. Your data stays in one workflow so updates are easier to track.
            </p>
        </section>
    </PublicLayout>
</template>

<style scoped>
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
</style>
