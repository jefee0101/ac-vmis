<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';
</script>

<template>
    <PublicLayout
        title="Contact"
        page-title="Contact and Support"
        page-description="Need help? Use these official channels for account and system concerns."
    >
        <section class="public-card reveal space-y-2 text-sm text-[color:var(--page-text)]">
            <p><strong>Official Email:</strong> varsity.support@asiancollege.edu.ph</p>
            <p><strong>Office Contact:</strong> +63 000 000 0000</p>
            <p><strong>Office:</strong> Student Affairs and Sports Development</p>
            <p class="support-note">
                For faster support, include your full name, role, and the issue you are reporting.
            </p>
        </section>

        <section class="public-card reveal space-y-2 text-sm text-[color:var(--page-text)]">
            <p class="font-semibold text-[color:var(--page-text)]">Socials</p>
            <p>
                <strong>Facebook:</strong>
                <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-link">@AsianCollegeVarsity</a>
            </p>
            <p>
                <strong>Instagram:</strong>
                <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-link">@asiancollege.varsity</a>
            </p>
            <p>
                <strong>YouTube:</strong>
                <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" class="social-link">Asian College Varsity</a>
            </p>
        </section>
    </PublicLayout>
</template>

<style scoped>
.support-note {
    margin-top: 0.35rem;
    color: var(--page-text-muted);
}

.social-link {
    color: var(--page-text);
    text-decoration: none;
}

.social-link:hover {
    text-decoration: underline;
}
</style>
