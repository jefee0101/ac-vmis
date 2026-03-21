<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/components/Public/PublicLayout.vue';
</script>

<template>
    <PublicLayout
        title="Policies"
        page-title="Policies"
        page-description="Read the rules on data use, user responsibility, and acceptable behavior in AC-VMIS."
    >
        <section class="public-card reveal">
            <p class="policy-intro">
                These policy pages explain what data is collected, why it is used, and what users are expected to follow.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <Link href="/privacy-policy" class="link-btn">Privacy Policy</Link>
                <Link href="/terms-of-use" class="link-btn">Terms of Use</Link>
            </div>
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

.policy-intro {
    color: #475569;
}

.link-btn {
    display: inline-flex;
    padding: 0.5rem 0.75rem;
    border-radius: 0.55rem;
    background: #1f2937;
    color: #fff;
    font-size: 0.875rem;
    text-decoration: none;
}
</style>
