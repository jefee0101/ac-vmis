<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';
</script>

<template>
    <PublicLayout
        title="Privacy Policy"
        page-title="Privacy Policy"
        page-description="How AC-VMIS collects, uses, and protects user data."
    >
        <section class="public-card reveal">
            <h2 class="section-title">What Data Is Collected</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Account and profile details</li>
                <li>Team, schedule, and attendance records</li>
                <li>Wellness and health clearance submissions</li>
                <li>Academic documents and eligibility results</li>
                <li>System announcements and notification logs</li>
            </ul>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Why Data Is Used</h2>
            <p>
                Data is used to run varsity operations, verify compliance, and keep students and coaches informed through updates and alerts.
            </p>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">How Data Is Protected</h2>
            <p>
                Access is limited by role and login permissions. The institution should maintain secure storage, backups, and proper account control.
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
    margin-bottom: 0.45rem;
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
}
</style>
