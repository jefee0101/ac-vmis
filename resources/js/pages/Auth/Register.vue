<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { useInertiaLoading } from '@/composables/useInertiaLoading'
import Spinner from '@/components/ui/spinner/Spinner.vue'

const { isLoading } = useInertiaLoading()

function toWelcome() {
    router.visit('/')
}
function toLogin() {
    router.visit('Login')
}
function registerStudentAthlete() {
    router.visit('Student-AthleteRegister')
}
function registerCoach() {
    router.visit('CoachRegister')
}
</script>

<template>
    <Head title="Register" />

    <div class="register-page">
        <header class="register-header px-4 pt-4 pb-4 sm:px-6 sm:pt-6 lg:px-10">
            <div v-if="isLoading" class="top-loading" />
            <div class="mx-auto max-w-6xl register-nav">
                <div>
                    <p class="kicker">Asian College</p>
                    <p class="brand">Varsity Management Information System</p>
                </div>
                <button @click="toWelcome" class="btn-outline" :disabled="isLoading">Back To Home</button>
            </div>
        </header>

        <main class="register-main px-4 py-8 sm:px-6 lg:px-10">
            <div class="mx-auto max-w-6xl register-grid">
                <section class="register-copy">
                    <p class="copy-kicker">Account Creation</p>
                    <h1>Choose Your Account Type</h1>
                    <p>
                        Select the role you want to register with so AC-VMIS can route your onboarding,
                        profile requirements, and approval workflow correctly.
                    </p>
                </section>

                <section class="register-card">
                    <button @click="registerStudentAthlete" class="role-btn role-btn-fill" :disabled="isLoading">
                        <span class="inline-flex items-center gap-2">
                            <Spinner v-if="isLoading" class="h-4 w-4 text-white" />
                            {{ isLoading ? 'Opening...' : 'Student-Athlete' }}
                        </span>
                    </button>
                    <button @click="registerCoach" class="role-btn role-btn-outline" :disabled="isLoading">
                        <span class="inline-flex items-center gap-2">
                            <Spinner v-if="isLoading" class="h-4 w-4 text-[#1f2937]" />
                            {{ isLoading ? 'Opening...' : 'Coach' }}
                        </span>
                    </button>
                    <p class="login-note">
                        Already registered?
                        <button @click="toLogin" class="login-link" :disabled="isLoading">Login</button>
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.register-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #1f2937;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
}

.register-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(2px);
    border-bottom:  1px solid rgba(15, 23, 42, 0.56);
}

.register-nav {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 14px;
    background: #ffffff;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.top-loading {
    height: 3px;
    width: 100%;
    border-radius: 999px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #1f2937 0%, #475569 40%, #94a3b8 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1s linear infinite;
}

.kicker {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: #94a3b8;
}

.brand {
    margin-top: 2px;
    font-size: 1rem;
    font-weight: 800;
    color: #1f2937;
}

.btn-outline {
    color: #1f2937;
    border: 1px solid rgba(15, 23, 42, 0.56);
    background: #ffffff;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 14px;
}

.btn-outline:disabled,
.role-btn:disabled,
.login-link:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.register-main {
    display: flex;
    align-items: center;
    min-height: calc(100vh - 110px);
}

.register-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1rem;
    align-items: center;
}

.register-copy {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 16px;
    background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
    padding: 1.25rem;
}

.copy-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    font-weight: 700;
}

.register-copy h1 {
    margin-top: 0.45rem;
    font-size: 2rem;
    line-height: 1.1;
    color: #1f2937;
    font-weight: 800;
}

.register-copy p {
    margin-top: 0.75rem;
    color: #64748b;
    line-height: 1.65;
    max-width: 52ch;
}

.register-card {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 16px;
    background: #ffffff;
    padding: 1.2rem;
    display: grid;
    gap: 0.75rem;
}

.role-btn {
    width: 100%;
    min-height: 66px;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 700;
}

.role-btn-fill {
    border: 1px solid #1f2937;
    background: #1f2937;
    color: #ffffff;
}

.role-btn-outline {
    border: 1px solid rgba(15, 23, 42, 0.56);
    background: #ffffff;
    color: #1f2937;
}

.login-note {
    margin-top: 0.35rem;
    text-align: center;
    color: #64748b;
    font-size: 0.9rem;
}

.login-link {
    border: none;
    background: none;
    color: #1f2937;
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

@media (max-width: 900px) {
    .register-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .brand {
        font-size: 0.9rem;
    }

    .register-main {
        min-height: auto;
    }

    .register-copy h1 {
        font-size: 1.6rem;
    }
}

@media (min-width: 1024px) {
    .register-page {
        font-size: 1.125rem;
    }
}

@keyframes loading-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
