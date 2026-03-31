<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue'
import { Head, router } from '@inertiajs/vue3'

function goHome() {
    router.visit('/')
}

function goContact() {
    router.visit('/contact')
}
</script>

<template>
    <Head title="Account Rejected" />

    <PublicLayout title="Account Rejected">
        <div class="status-shell">
            <section class="public-card status-card">
                <p class="status-kicker">Registration Update</p>
                <h2>Account Rejected</h2>
                <p class="status-text">
                    Your account registration has been <span class="status-rejected">rejected</span>.
                    Please check your email for the reason and next steps before submitting again.
                </p>

                <div class="status-actions">
                    <button @click="goHome" class="status-btn-fill">Back to Home</button>
                    <button @click="goContact" class="status-btn-outline">Contact Support</button>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>

<style scoped>
.status-shell {
    min-height: 52vh;
    display: grid;
    place-items: center;
}

.status-card {
    width: min(100%, 620px);
    text-align: center;
    display: grid;
    gap: 0.5rem;
}

.status-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.status-card h2 {
    margin-top: 0.45rem;
    color: #ffffff;
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
}

.status-text {
    margin-top: 0.75rem;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
}

.status-rejected {
    color: #fecaca;
    font-weight: 700;
}

.status-actions {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.status-btn-fill,
.status-btn-outline {
    border-radius: 10px;
    padding: 0.62rem 0.95rem;
    font-weight: 700;
    font-size: 0.9rem;
}

.status-btn-fill {
    border: 1px solid #ffffff;
    background: #ffffff;
    color: #034485;
}

.status-btn-outline {
    border: 1px solid rgba(255, 255, 255, 0.6);
    background: transparent;
    color: #ffffff;
}
</style>
