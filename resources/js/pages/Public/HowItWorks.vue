<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';

const steps = [
    'Join tryouts first. Student-athletes can register only after a coach confirms they are part of the varsity roster.',
    'Once selected, create your student-athlete account (or coach account if you are staff).',
    'Submit the required documents during signup.',
    'Wait for account approval before full access.',
    'Use daily features for schedules, attendance, and wellness, and keep academic/health requirements updated.',
];
</script>

<template>
    <PublicLayout
        title="How It Works"
        page-title="How AC-VMIS Works"
        page-description="Five simple steps from registration to daily varsity use."
    >
        <section class="public-card reveal">
            <ol class="space-y-2 text-sm text-[color:var(--page-text)]">
                <li v-for="(step, index) in steps" :key="step"><strong>{{ index + 1 }}.</strong> {{ step }}</li>
            </ol>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">After Approval</h2>
            <p>
                Log in and use your role-based pages right away. Student-athletes stay linked to their coach’s roster so schedules,
                attendance, and wellness updates flow in one place.
            </p>
        </section>
    </PublicLayout>
</template>
