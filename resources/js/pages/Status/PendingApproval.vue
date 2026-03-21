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
    <PublicLayout
        title="Account Pending Approval"
        page-title="Account Pending Approval"
        page-description="Your account was created successfully and is now waiting for approval."
    >
        <div class="status-shell">
            <section class="status-card">
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
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 1.3rem;
    text-align: center;
}

.status-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    font-weight: 700;
}

.status-card h2 {
    margin-top: 0.45rem;
    color: #1f2937;
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
}

.status-text {
    margin-top: 0.75rem;
    color: #64748b;
    line-height: 1.6;
}

.status-pending {
    color: #f53003;
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
    border: 1px solid #1f2937;
    background: #1f2937;
    color: #fff;
}

.status-btn-outline {
    border: 1px solid rgba(15, 23, 42, 0.56);
    background: #fff;
    color: #1f2937;
}
</style>
