<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';
</script>

<template>
    <PublicLayout
        title="Terms of Use"
        page-title="Terms of Use"
        page-description="User responsibilities and acceptable use of AC-VMIS."
    >
        <section class="public-card reveal">
            <h2 class="section-title">User Responsibility</h2>
            <p>
                Keep your account secure and submit truthful information. You are responsible for actions made using your login.
            </p>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Acceptable Use</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>Use AC-VMIS only for valid varsity and school-related activities.</li>
                <li>Do not upload false or misleading records.</li>
                <li>Do not access data that is outside your role permissions.</li>
                <li>Do not misuse personal information or uploaded files.</li>
            </ul>
        </section>

        <section class="public-card reveal">
            <h2 class="section-title">Account Actions</h2>
            <p>
                Accounts may be restricted, rejected, or suspended when policies or security rules are violated.
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
