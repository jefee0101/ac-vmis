<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';
</script>

<template>
    <PublicLayout
        title="About AC-VMIS"
        page-title="About AC-VMIS"
        page-description="AC-VMIS keeps varsity work simple, organized, and easy to follow for student-athletes and coaches."
    >
        <section class="public-card reveal">
            <h2 class="section-title">What Problem It Solves</h2>
            <p>
                Varsity updates are often scattered across chats, paper logs, and separate files. AC-VMIS puts core tasks in one place
                so users can act faster and avoid missed updates.
            </p>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Who Benefits</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Student-athletes who need one place for schedules, attendance, wellness, and academic status</li>
                <li>Coaches who need clear team records and consistent session monitoring</li>
                <li>School staff who need structured approval and compliance visibility</li>
            </ul>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Main Features</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Account Onboarding</li>
                <li>Team Management</li>
                <li>Schedule Management</li>
                <li>Attendance Verification</li>
                <li>Wellness Monitoring</li>
                <li>Academic Eligibility</li>
                <li>Announcements & Alerts</li>
            </ul>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Scope Note</h2>
            <p>
                AC-VMIS is a management and tracking system. It does not provide medical diagnosis, treatment management, or legal advice.
            </p>
        </section>
    </PublicLayout>
</template>
