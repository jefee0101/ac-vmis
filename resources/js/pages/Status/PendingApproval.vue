<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue'
import { router } from '@inertiajs/vue3'

function goHome() {
    router.visit('/')
}

function goLogin() {
    router.visit('/Login')
}
</script>

<template>
    <PublicLayout title="Account Pending Approval">
        <div class="status-shell">
            <section class="public-card status-card">
                <p class="status-kicker">Registration Submitted</p>
                <h2>Account Created Successfully</h2>
                <p class="status-text">
                    Your registration is currently <span class="status-pending">pending approval</span>.
                    An administrator will review your details. You can log in once your account is approved.
                </p>

                <div class="status-actions">
                    <button @click="goHome" class="status-btn-fill">Back to Home</button>
                    <button @click="goLogin" class="status-btn-outline">Go to Login</button>
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

.status-pending {
    color: #fde68a;
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
